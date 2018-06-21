<?php

namespace BadChoice\Mojito\Models;

use BadChoice\Grog\Traits\SaveNestedTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryContent extends Model
{
    protected $guarded  = [];
    protected $appends  = ["itemName"];
    protected $lastInventory;

    use SoftDeletes;
    use SaveNestedTrait;

    public function inventory()
    {
        return $this->belongsTo(config('mojito.inventoryClass', 'Inventory'));
    }

    public function item()
    {
        return $this->belongsTo(config('mojito.itemClass', 'Item'));
    }

    public function getItemNameAttribute()
    {
        return nameOrDash($this->item);
    }

    public function approve()
    {
        $this->calculateFields();
        $this->updateStock();
    }

    protected function calculateFields()
    {
        $inventoryClass             = config('mojito.inventoryClass');
        $lastInventory              = $this->getLastInventory($inventoryClass);
        $this->previousQuantity     = $lastInventory ? $lastInventory->contents()->where('item_id', $this->item_id)->first()->quantity ?? 0 : 0;
        $this->stockCost            = $this->item->costPrice * $this->quantity;

        $stockClass                 = config('mojito.stockClass');
        $this->expectedQuantity     = $stockClass::findWith($this->item_id, $this->inventory->warehouse_id)->quantity ?? 0;
        $this->variance             = $this->quantity - $this->expectedQuantity;
        $this->stockDeficitCost     = $this->stockCost / $this->quantity * $this->variance;

        $this->consumedSinceLastInventory = $this->getQuantityConsumedSinceLastInventory($lastInventory->closed_at ?? null);
        $this->consumptionCost            = $this->stockCost / $this->quantity * $this->consumedSinceLastInventory;

        $this->stockIn              = $this->getStockInSinceLastInventory($lastInventory->closed_at ?? null);
        $this->estimatedDaysLeft    = $this->getEstimatedDaysLeft($lastInventory->closed_at ?? null);
        $this->save();
    }

    protected function updateStock()
    {
        $stockClass                 = config('mojito.stockClass');
        $stock                      = $stockClass::findWith($this->item_id, $this->inventory->warehouse_id);
        if (! $stock) {
            return $stockClass::create(["warehouse_id" => $this->inventory->warehouse_id, "item_id" => $this->item_id, "quantity" => $this->quantity]);
        }
        return $stock->update(["quantity" => $this->quantity]);
    }

    protected function getLastInventory($inventoryClass)
    {
        if (! $this->lastInventory) {
            $this->lastInventory = $inventoryClass::approved()->where("warehouse_id", $this->inventory->warehouse_id)->orderBy('updated_at', 'desc')->first();
        }
        return $this->lastInventory;
    }

    protected function getQuantityConsumedSinceLastInventory($lastInventoryClosedAt)
    {
        return StockMovement::where("to_warehouse_id", $this->inventory->warehouse_id)
            ->where('item_id', $this->item_id)
            ->where("action", Warehouse::ACTION_ADD)
            ->where("quantity", "<", 0)
            ->whereBetween("created_at", [$lastInventoryClosedAt, $this->inventory->closed_at])
            ->sum('quantity');  // TODO: multiple by units?;
    }

    protected function getStockInSinceLastInventory($lastInventoryClosedAt)
    {
        return StockMovement::where("to_warehouse_id", $this->inventory->warehouse_id)
            ->where('item_id', $this->item_id)
            ->whereBetween("created_at", [$lastInventoryClosedAt, $this->inventory->closed_at])
            ->where(function ($query) {
                $query->where("action", Warehouse::ACTION_ADD)->where("quantity", ">", 0)
                    ->orWhere(function ($query) {
                        $query->where("action", Warehouse::ACTION_MOVE)->where("to_warehouse_id", $this->inventory->warehouse_id);
                    });
            })->sum('quantity');  // TODO: multiple by units?;
    }

    protected function getEstimatedDaysLeft($lastInventoryClosedAt)
    {
        if (! $lastInventoryClosedAt || $this->consumedSinceLastInventory == 0) {
            return 0;
        }
        return intval($this->quantity / ($this->consumedSinceLastInventory / (Carbon::parse($this->inventory->closed_at)->diff(Carbon::parse($lastInventoryClosedAt))->days)));
    }
}

<?php

namespace BadChoice\Mojito\Models;

use BadChoice\Grog\Traits\SaveNestedTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryContent extends Model
{
    protected $guarded  = [];
    protected $appends  = ["itemName"];
    protected $lastInventory;
    private $stockClass;
    private $stockMovementClass;
    private $warehouseClass;
    private $inventoryClass;

    use SoftDeletes;
    use SaveNestedTrait;

    public function inventory()
    {
        return $this->belongsTo(config('mojito.inventoryClass', 'Inventory'));
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->setExpectedQuantity();
        });
    }

    public function setExpectedQuantity()
    {
        $stockClass             = config('mojito.stockClass', 'Stock');
        $this->expectedQuantity = $stockClass::findWith($this->item_id, $this->inventory->warehouse_id)->quantity ?? 0;
        $this->variance         = $this->quantity - $this->expectedQuantity;
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
        $this->setClassFields();
        $this->calculateFields();
        $this->updateStock();
    }

    protected function calculateFields()
    {
        $lastInventory              = $this->getLastInventory();
        $consumedSinceLastInventory = $this->getQuantityConsumedSince($lastInventory->closed_at ?? null);
        $this->update([
            "previousQuantity"              => $lastInventory ? $lastInventory->contents()->where('item_id', $this->item_id)->first()->quantity ?? 0 : 0,
            "stockCost"                     => $this->item->costPrice * $this->quantity,
            "stockDeficitCost"              => $this->item->costPrice * $this->variance,
            "consumedSinceLastInventory"    => $consumedSinceLastInventory,
            "consumptionCost"               => $this->item->costPrice * $consumedSinceLastInventory,
            "stockIn"                       => $this->getStockInSince($lastInventory->closed_at ?? null),
            "estimatedDaysLeft"             => $this->getEstimatedDaysLeft($lastInventory->closed_at ?? null, $consumedSinceLastInventory),
        ]);
    }

    protected function updateStock()
    {
        return $this->stockClass::updateOrCreate([
            "item_id"       => $this->item_id,
            "warehouse_id"  => $this->inventory->warehouse_id,
        ], [
            "quantity" => $this->quantity + StockMovement::where("created_at", ">", $this->inventory->closed_at ?? $this->item->created_at)->sum('quantity'),
        ]);
    }

    protected function getLastInventory()
    {
        if (! $this->lastInventory) {
            $this->lastInventory = $this->inventoryClass::approved()
                ->where('warehouse_id', $this->inventory->warehouse_id)->whereHas('contents', function ($query) {
                    $query->where('item_id', $this->item_id);
                })->latest('closed_at')->first();
        }
        return $this->lastInventory;
    }

    protected function getQuantityConsumedSince($lastInventoryClosedAt)
    {
        // Since we are working on same warehouse, units are the same and no conversion is required
        return $this->consumedQuantityWithAdd($lastInventoryClosedAt) * -1
            + $this->consumedQuantityWithMove($lastInventoryClosedAt);
    }

    protected function consumedQuantityWithAdd($lastInventoryClosedAt)
    {
        return $this->stockMovementsQuery($lastInventoryClosedAt)
            ->where("action", $this->warehouseClass::ACTION_ADD)
            ->where("to_warehouse_id", $this->inventory->warehouse_id)
            ->where("quantity", "<", 0)->sum('quantity');
    }

    protected function consumedQuantityWithMove($lastInventoryClosedAt)
    {
        return $this->stockMovementsQuery($lastInventoryClosedAt)->where("action", $this->warehouseClass::ACTION_MOVE)->where("from_warehouse_id", $this->inventory->warehouse_id)->sum('quantity');
    }

    protected function getStockInSince($lastInventoryClosedAt)
    {
        return $this->stockMovementsQuery($lastInventoryClosedAt)
            ->where("to_warehouse_id", $this->inventory->warehouse_id)
            ->where(function ($query) {
                $query->where("action", $this->warehouseClass::ACTION_ADD)
                       ->where("quantity", ">", 0)
                       ->orWhere(function ($query) {
                           $query->where("action", $this->warehouseClass::ACTION_MOVE)->where("to_warehouse_id", $this->inventory->warehouse_id);
                       });
            })->sum('quantity');  // Since we are working on same warehouse, units are the same and no conversion is required
    }

    protected function stockMovementsQuery($lastInventoryClosedAt)
    {
        return $this->stockMovementClass::where('item_id', $this->item_id)
            ->whereBetween("created_at", [$lastInventoryClosedAt ? : $this->item->created_at, $this->inventory->closed_at]);
    }

    protected function getEstimatedDaysLeft($lastInventoryClosedAt, $consumedSinceLastInventory)
    {
        if (! $lastInventoryClosedAt || $consumedSinceLastInventory == 0) {
            return 0;
        }
        $diffInDays = $this->inventory->closed_at->diffInDays($lastInventoryClosedAt);
        if (! $diffInDays) {
            return 0;
        }
        return number_format($this->quantity / ($consumedSinceLastInventory / $diffInDays), 2);
    }

    protected function setClassFields()
    {
        $this->stockClass         = config('mojito.stockClass', 'Stock');
        $this->stockMovementClass = config('mojito.stockMovementClass', 'StockMovement');
        $this->warehouseClass     = config('mojito.warehouseClass', 'Warehouse');
        $this->inventoryClass     = config('mojito.inventoryClass', 'Inventory');
    }
}

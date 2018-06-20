<?php

namespace BadChoice\Mojito\Models;

use BadChoice\Grog\Traits\SaveNestedTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryContent extends Model
{
    protected $guarded  = [];
    protected $appends  = ["variance", "consumptionCost", "stockConsumed"];

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

    public function getVarianceAttribute()
    {
        return $this->stock - $this->expectedStock;
    }

    public function getConsumptionCostAttribute()
    {
        return $this->stockCost / $this->stock * $this->stockConsumed;
    }

    public function getStockConsumedAttribute()
    {
        return $this->previousManagerStock - $this->stock;
    }

    public function approve()
    {
        $this->calculateFields();
        $this->updateStock();
    }

    protected function calculateFields()
    {
        $inventoryClass             = config('mojito.inventoryClass', 'Inventory');
        $lastInventory              = $inventoryClass::approved()->where("warehouse_id", $this->inventory->warehouse_id)->orderBy('updated_at', 'desc')->first();
        $this->previousManagerStock = $lastInventory ? $lastInventory->contents()->where('item_id', $this->item_id)->first()->stock ?? 0 : 0;
        $this->itemName             = nameOrDash($this->item);
        $this->stockCost            = $this->item->costPrice * $this->stock;
        $this->expectedStock        = $this->item->stocks()->where('warehouse_id', $this->inventory->warehouse_id)->sum('quantity');
        
        // $table->decimal("previousAuditStock", 8, 3)->default(0);   // TODO: Ask for it
        // $table->decimal("surplusDeficitCost", 8, 3)->default(0);   // TODO: Ask for it   stockCost/stock*variance
        // $table->decimal("maxRetailPrice", 8, 3)->default(0);       // TODO: Ask for it
        //   $table->decimal("stockIn", 8, 3);              // TODO: Ask for it
        //   $table->decimal("stockUsageEPOS", 8, 3);       // TODO: Ask for it
        //   $table->decimal("salesRetailGross", 8, 3);     // TODO: Ask for it
        //   $table->decimal("GPPercent", 8, 3);            // TODO: Ask for it
        //   $table->decimal("EstDaysStock", 8, 3);         // TODO: Ask for it
        $this->save();
    }

    protected function updateStock()
    {
        $stocks = $this->item->stocks()->where('warehouse_id', $this->inventory->warehouse_id);
        if ($stocks->count() == 1) {
            return $stocks->first()->update(["quantity" => $this->stock]);
        }
        if ($stocks->count() > 1) {
            $stocks->delete();
        }
        $stockClass = config('mojito.stockClass');
        return $stockClass::create(["warehouse_id" => $this->inventory->warehouse_id, "item_id" => $this->item_id, "quantity" => $this->stock]);
    }
}

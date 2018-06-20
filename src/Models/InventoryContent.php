<?php

namespace BadChoice\Mojito\Models;

use BadChoice\Grog\Traits\SaveNestedTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryContent extends Model
{
    protected $guarded  = [];
    protected $appends  = ["variance", "consumptionCost"];

    use SoftDeletes;
    use SaveNestedTrait;

    public function inventory()
    {
        return $this->belongsTo(config('mojito.inventoryClass', 'Inventory'));
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model)
        {
            $model->calculateStockConsumed();
        });
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

    protected function calculateStockConsumed()
    {
        $inventoryClass = config('mojito.inventoryClass', 'Inventory');
        $lastInventory  = $inventoryClass::approved()->where("warehouse_id", $this->inventory->warehouse_id)->orderBy('updated_at', 'desc')->first();
        $this->attributes["previousManagerStock"] = $lastInventory ? $lastInventory->contents()->where('item_id', $this->item_id)->first()->stock ?? 0 : 0;
        $this->attributes["stockConsumed"]  = $this->attributes["previousManagerStock"] - $this->stock;
        $this->attributes["itemName"]       = nameOrDash($this->item);
        $this->attributes["stockCost"]      = $this->item->costPrice * $this->stock;

        $this->attributes["expectedStock"]  = $this->item->stocks()->where('warehouse_id', $this->inventory->warehouse_id)->sum('quantity');
        // $table->decimal("previousAuditStock", 8, 3)->default(0);   // TODO: Ask for it
        // $table->decimal("surplusDeficitCost", 8, 3)->default(0);   // TODO: Ask for it   stockCost/stock*variance
        // $table->decimal("maxRetailPrice", 8, 3)->default(0);       // TODO: Ask for it
        //   $table->decimal("stockIn", 8, 3);              // TODO: Ask for it
        //   $table->decimal("stockUsageEPOS", 8, 3);       // TODO: Ask for it
        //   $table->decimal("salesRetailGross", 8, 3);     // TODO: Ask for it
        //   $table->decimal("GPPercent", 8, 3);            // TODO: Ask for it
        //   $table->decimal("EstDaysStock", 8, 3);         // TODO: Ask for it
    }
}

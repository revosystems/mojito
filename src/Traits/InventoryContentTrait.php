<?php

namespace BadChoice\Mojito\Traits;

use BadChoice\Grog\Traits\SaveNestedTrait;
use Illuminate\Database\Eloquent\SoftDeletes;

trait InventoryContentTrait
{
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
        $lastInventory  = $inventoryClass::approved()->latest()->first();
        $this->attributes["previousManagerStock"] = $lastInventory ? $lastInventory->contents()->where('item_id', $this->item_id)->first()->stock ?? 0 : 0;

        $this->attributes["stockConsumed"]  = $this->attributes["previousManagerStock"] - $this->stock;
        $this->attributes["itemName"]       = nameOrDash($this->item);
        $this->attributes["stockCost"]      = $this->item->costPrice * $this->stock;
//        $table->decimal('expectedStock', 8, 3)->nullable();       // TODO: calculate it based on sales from last inventory
//        $table->decimal("previousAuditStock", 8, 3)->default(0);  // TODO: Ask for it
    }
}

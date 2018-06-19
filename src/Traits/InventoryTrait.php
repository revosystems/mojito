<?php

namespace BadChoice\Mojito\Traits;

use BadChoice\Grog\Traits\SaveNestedTrait;
use Illuminate\Database\Eloquent\SoftDeletes;

trait InventoryTrait
{
    use SoftDeletes;
    use SaveNestedTrait;

    public function warehouse()
    {
        return $this->belongsTo(config('mojito.warehouseClass', 'Warehouse'), 'warehouse_id');
    }

    public function contents()
    {
        return $this->hasMany(config('mojito.inventoryContentClass', 'InventoryContent'));
    }

    public function scopeApproved($query)
    {
        return $query->where('status', static::STATUS_APPROVED);
    }

    /*
    public function addContent($itemId, $quantity, $unitId = 1)
    {
        $this->contents()->updateOrCreate([
            "item_id"               => $itemId,
        ], [
            "real_quantity"         => $quantity,
            "expected_quantity"  => $this->warehouse->stocks()->where("item_id", $itemId)->sum('quantity'),  // Should be add here or on inventory close?
            "unit_id"               => $unitId,
        ]);
    }
    */
}

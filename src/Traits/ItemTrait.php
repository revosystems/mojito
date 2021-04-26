<?php

namespace BadChoice\Mojito\Traits;

use BadChoice\Mojito\Models\Assembly;
use BadChoice\Mojito\Models\Unit;
use BadChoice\Mojito\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;

trait ItemTrait
{
    //=============================================================================
    // SCOPES
    //=============================================================================
    public function scopeWithStockManagement($query)
    {
        $usesStockManagementKey = config('mojito.usesStockManagementKey');
        return $query->where($usesStockManagementKey, '=', 1);
    }

    public function scopeWithStockManagementAndAssembly($query)
    {
        $usesStockManagementKey = config('mojito.usesStockManagementKey');
        return $query->where($usesStockManagementKey, '=', 1)->has('assembliesForScope');
    }

    public function scopeWithStockManagementAndNoAssembly($query)
    {
        $usesStockManagementKey = config('mojito.usesStockManagementKey');
        return $query->where($usesStockManagementKey, '=', 1)->doesntHave('assembliesForScope');
    }

    //=============================================================================
    // RELATIONSHIPS
    //=============================================================================
    public function warehouses()
    {
        //return $this->belongsToMany(config('mojito.warehouseClass'),config('mojito.stocksTable'),'item_id','warehouse_id')/*->withPivot('id','quantity','unit_id','defaultQuantity','alert','deleted_at')->withTimestamps()->wherePivot('deleted_at','=',null)*/;
        return $this->belongsToMany(config('mojito.warehouseClass'), config('mojito.stocksTable'), 'item_id', 'warehouse_id')->withPivot('id', 'quantity', 'unit_id', 'defaultQuantity', 'alert', 'deleted_at')->withTimestamps()->wherePivot('deleted_at', '=', null);
    }

    public function stocks()
    {
        return $this->hasMany(config('mojito.stockClass'), 'item_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function vendors()
    {
        return $this->belongsToMany(config('mojito.vendorClass'), config('mojito.vendorItemsTable'), 'item_id', 'vendor_id')->withPivot('id', 'costPrice', 'unit_id', 'reference', 'tax_id', 'pack')->wherePivot('deleted_at', '=', null);
    }

    //=============================================================================
    // ASSEMBLIES
    //=============================================================================

    /**
     * @return All the assemblies
     */
    public function assemblies()
    {
        return $this->belongsToMany(config('mojito.itemClass', 'Item'), config('mojito.assembliesTable', 'assemblies'), 'main_item_id', 'item_id')->withPivot('quantity', 'id', 'unit_id', 'deleted_at')->withTimestamps()->wherePivot('deleted_at', '=', null)->using(config('mojito.assemblyClass', 'Assembly'));
    }

    /**
     * Same function as assemblies but without all the Pivot related data (so we can use it for scopes)
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function assembliesForScope()
    {
        return $this->belongsToMany(config('mojito.itemClass', 'Item'), config('mojito.assembliesTable', 'assemblies'), 'main_item_id', 'item_id')->withTimestamps()->whereNull(config('mojito.assembliesTable', 'assemblies').'.deleted_at');
    }

    /**
     * @return mixed the Items where this item is assembled to
     */
    public function assembledTo()
    {
        return $this->belongsToMany(config('mojito.itemClass', 'Item'), config('mojito.assembliesTable', 'assemblies'), 'item_id', 'main_item_id')
            ->withPivot('quantity', 'id', 'unit_id', 'deleted_at')->withTimestamps()->wherePivot('deleted_at', '=', null)->using(config('mojito.assemblyClass', 'Assembly'));
    }

    public function assemblyPrice()
    {
        return $this->assemblies->sum(function ($assembly) {
            $finalQty = Unit::convert($assembly->pivot->quantity, $assembly->pivot->unit, $assembly->unit);
            return $assembly->costPrice * $finalQty;
        });
    }
    
    public function finalCostPrice()
    {
        return $this->hasAssemblies() ? $this->assemblyPrice() : $this->costPrice;
    }

    public function hasAssemblies()
    {
        return count($this->assemblies) > 0;
    }

    //=============================================================================
    // STOCK
    //=============================================================================

    /**
     * Sums the stock for each warehouse and returns total stock for item
     * @return float total stock
     */
    public function calculateStock()
    {
        //TODO: Should take units in account...
        return $this->stocks->sum('quantity');
    }

    /**
     * Decrease item stock, if it has assembly items, the item itself is not decreased
     * @param $qty the quantity to decrease, if quantity is <0 the items will be added
     * @param $warehouse the warehouse were we are decreasing the inventory (if it is there)
     * @param int $weight 1 the weight that is discounted if item uses weight
     * @param int $unit_id the unit id if other than the item itself
     * @param $action
     */
    public function decreaseStock($qty, $warehouse, $weight = 1, $unit_id = null, $action = Warehouse::ACTION_SALE)
    {
        $usesStockManagementKey = config('mojito.usesStockManagementKey');
        if ($warehouse == null || ! $this->$usesStockManagementKey || $qty == 0) {
            return;
        }

        if ($unit_id == null) {
            $unit_id = $this->unit_id;
        }

        if ($this->usesWeight) {
            $qty *= $weight;
        }

        if ($this->hasAssemblies()) {
            foreach ($this->assemblies as $assembledItem) {
                if ($assembledItem->$usesStockManagementKey && $stock = $warehouse->stockByItem($assembledItem)) { //If item is in warehouse
                    $stock->decrease($qty * $assembledItem->pivot->quantity, $assembledItem->pivot->unit_id, $action);
                }
            }
            return;
        }
        if ($stock = $warehouse->stockByItem($this)) { //If item is in warehouse
            $stock->decrease($qty, $unit_id, $action);
        }
    }

    public static function syncStock($fromDate, $warehouse_id = null)
    {
        $stockClass         = config('mojito.stockClass', 'Stock');
        $inventorySynced    = $stockClass::sync($fromDate);
        $synced             = [];

        if ($inventorySynced["new"] != null) {
            foreach ($inventorySynced["new"] as $object) {
                if ($object["warehouse_id"] == $warehouse_id) {
                    $synced[] = ["id" => $object["item_id"], "inventory" => $object["quantity"], "alert" => $object["alert"]];
                }
            }
        }
        if ($inventorySynced["updated"] != null) {
            foreach ($inventorySynced["updated"] as $object) {
                if ($object["warehouse_id"] == $warehouse_id) {
                    $synced[] = ["id" => $object["item_id"], "inventory" => $object["quantity"], "alert" => $object["alert"]];
                }
            }
        }
        if ($inventorySynced["deleted"] != null) {
            foreach ($inventorySynced["deleted"] as $object) {
                if ($object["warehouse_id"] == $warehouse_id) {
                    $synced[] = ["id" => $object["item_id"], "inventory" => 0, "alert" => $object["alert"]];
                }
            }
        }

        return $synced;
    }

    //=============================================================================
    // DELETE
    //=============================================================================
    public function deleteStockRelations()
    {
        $stockClass = config('mojito.stockClass', 'Stock');
        $stockClass::byItem($this->id)->delete();
        Assembly::byMainItem($this->id)->delete();
        $vendorItemClass = config('mojito.vendorItemClass', 'VendorItemPivot');
        $vendorItemClass::byItem($this->id)->delete();
    }
}

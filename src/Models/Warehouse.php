<?php

namespace BadChoice\Mojito\Models;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Warehouse extends Model
{
    use SoftDeletes;

    protected $table        = "warehouses";
    protected $dates        = ['deleted_at'];
    protected $hidden       = ['created_at','updated_at','deleted_at','unit_id'];
    protected $guarded      = [];

    protected static $rules = [
        'name'  => 'required|min:3',
    ];

    const ACTION_ADD                = 0;
    const ACTION_MOVE               = 1;
    const ACTION_SET_INVENTORY      = 2;
    const ACTION_SALE               = 3;

    //============================================================================
    // RELATIONSHIPS
    //============================================================================
    public function stocks()
    {
        return $this->hasMany(config('mojito.stockClass'), 'warehouse_id');
    }

    public function stockByItem($menuItem)
    {
        $stockClass = config('mojito.stockClass', 'Stock');
        return $stockClass ::where('warehouse_id', '=', $this->id)->where('item_id', '=', $menuItem->id)->first();
    }

    public function stocksToRefill()
    {
        return $this->hasMany(config('mojito.stockClass'), 'warehouse_id')->where('defaultQuantity', '>', DB::raw('quantity'));
    }

    //============================================================================
    // METHODS
    //============================================================================
    public static function canBeDeleted($id)
    {
        $stockClass = config('mojito.stockClass', 'Stock');
        if (count($stockClass::byWarehouse($id)->get()) > 0) {
            throw new Exception(trans('admin.notEmpty'));
        }
        return true;
    }

    public static function actionName($action)
    {
        if ($action == Warehouse::ACTION_ADD) {
            return trans('admin.add');
        }
        if ($action == Warehouse::ACTION_MOVE) {
            return trans('admin.move');
        }
        if ($action == Warehouse::ACTION_SET_INVENTORY) {
            return trans('admin.setInventory');
        }
    }

    public function delete()
    {
        $stockClass = config('mojito.stockClass', 'Stock');
        foreach ($stockClass::byWarehouse($this->id)->get() as $object) {
            $object->delete();
        }
        return parent::delete();
    }

    /**
     * Add qty stock to MenuItem with id $itemId, if this item doesn't exist for the warehouse it is created
     *
     * @param $itemId
     * @param $qty
     * @param $unit_id to to the conversion
     * @param bool $isSale
     * @return bool if can be added
     */
    public function add($itemId, $qty, $unit_id = null, $isSale = false)
    {
        $stockClass = config('mojito.stockClass', 'Stock');
        $pivot      = $stockClass::where('warehouse_id', '=', $this->id)->where('item_id', '=', $itemId)->first();

        if ($pivot == null) {
            $this->setInventory($itemId, $qty, $unit_id, $isSale);
        } else {
            $qty    = Unit::convert($qty, $unit_id, $pivot->unit_id);
            $pivot->update(["quantity" => $pivot->quantity + $qty]);
            StockMovement::create([
                'item_id'           => $itemId,
                'to_warehouse_id'   => $this->id,
                'quantity'          => $qty,
                'action'            => $isSale ? Warehouse::ACTION_SALE : Warehouse::ACTION_ADD
            ]);
            return true;
        }
        return false;
    }

    /*
     * Move qty stock of MenuItem from this warehouse to $warehouseId, if this item doesn't exist on toWarehouse it is created
     * It needs to exist en warehouse to be able to be moved
     *
     * @param $itemId the item we want to move
     * @param $toWarehouseId the warehouse we want to move to
     * @param $qty the quantity we are moving
     */
    public function move($itemId, $toWarehouseId, $qty)
    {
        $stockClass = config('mojito.stockClass', 'Stock');
        $pivotFrom  = $stockClass::where('warehouse_id', '=', $this->id)->where('item_id', '=', $itemId)->first();
        if ($pivotFrom == null) {
            return false;
        }

        $pivotTo = $stockClass::where('warehouse_id', '=', $toWarehouseId)->where('item_id', '=', $itemId)->first();

        if ($pivotTo != null) {
            $destQty = Unit::convert($qty, $pivotFrom->unit_id, $pivotTo->unit_id);
        } else {
            $destQty = $qty;
        }

        if ($pivotTo == null) {
            $stockClass::create([
                'warehouse_id' => $toWarehouseId,
                'item_id'      => $itemId,
                'quantity'     => $qty,
                'unit_id'      => $pivotFrom->unit_id,
                'alert'        => 0,
            ]);
        } else {
            $pivotTo->update(["quantity" => $pivotTo->quantity + $destQty]);
        }
        $pivotFrom->update(["quantity" => $pivotFrom->quantity - $qty]);

        StockMovement::create([
            'item_id'           => $itemId,
            'from_warehouse_id' => $this->id,
            'to_warehouse_id'   => $toWarehouseId,
            'quantity'          => $qty,
            'action'            => Warehouse::ACTION_MOVE
        ]);

        return true;
    }

    /**
     * Sets the quantity $qty to the item at warehouse, the previous data will not be taken in account,
     * if item doesn't exist on that warehouse it will be created
     *
     * @param $itemId
     * @param $qty
     * @param $unit_id to to the conversion
     * @param bool $isSale
     * @return bool
     */

    public function setInventory($itemId, $qty, $unit_id = null, $isSale = false)
    {
        $stockClass = config('mojito.stockClass','Stock');
        $pivot      = $stockClass::where('warehouse_id', '=', $this->id)->where('item_id','=',$itemId)->first();

        if ($pivot == null) {
            if ($unit_id == null) {
                $itemClass = config('mojito.itemClass', 'Item');
                $unit_id   = $itemClass::find($itemId)->unit_id;
            }
            $stockClass::create([
                'warehouse_id' => $this->id,
                'item_id'      => $itemId,
                'quantity'     => $qty,
                'alert'        => 0,
                'unit_id'      => $unit_id
            ]);

        }
        else{
            $pivot->update(["quantity" => $qty]);
        }

        StockMovement::create([
            'item_id'           => $itemId,
            'to_warehouse_id'   => $this->id,
            'quantity'          => $qty,
            'action'            => $isSale ? Warehouse::ACTION_SALE : Warehouse::ACTION_SET_INVENTORY
        ]);
        return true;
    }
}

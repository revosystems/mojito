<?php

namespace BadChoice\Mojito\Models;

use DateTimeInterface;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Warehouse extends Model
{
    use SoftDeletes;

    protected $table = "warehouses";
    protected $casts = [
        'deleted_at' => 'datetime',
    ];
    protected $hidden = ['created_at','updated_at','deleted_at','unit_id'];
    protected $guarded = [];

    protected static $rules = [
        'name'  => 'required|min:3',
    ];

    const ACTION_ADD           = 0;
    const ACTION_MOVE          = 1;
    const ACTION_SET_INVENTORY = 2;
    const ACTION_SALE          = 3;

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

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
        return $stockClass::where('warehouse_id', '=', $this->id)->where('item_id', '=', $menuItem->id)->first();
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
        if ($action == Warehouse::ACTION_SALE) {
            return trans_choice('admin.sale', 1);
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
     */
    public function add(int $itemId, float $quantity, ?int $unitId = null)
    {
        if (! $stock = $this->stocks()->where('item_id', $itemId)->first()) {
            return $this->setInventory($itemId, $quantity, $unitId);
        }
        $quantity    = Unit::convert($quantity, $unitId, $stock->unit_id);
        $stock->update(["quantity" => $stock->quantity + $quantity]);
        $stockMovementClass = config('mojito.stockMovementClass', 'StockMovement');
        return $stockMovementClass::create([
            'item_id'           => $itemId,
            'to_warehouse_id'   => $this->id,
            'quantity'          => $quantity,
            'action'            => Warehouse::ACTION_ADD
        ]);
    }

    /*
     * Move qty stock of MenuItem from this warehouse to $warehouseId, if this item doesn't exist on toWarehouse it is created
     * It needs to exist en warehouse to be able to be moved
     */
    public function move(int $itemId, int $toWarehouseId, float $quantity)
    {
        if (! $stockFrom = $this->stocks()->where('item_id', $itemId)->first()) {
            return null;
        }

        static::updateStock($itemId, $toWarehouseId, $quantity, $stockFrom->unit_id);
        $stockFrom->update(["quantity" => $stockFrom->quantity - $quantity]);
        $stockMovementClass = config('mojito.stockMovementClass', 'StockMovement');
        return $stockMovementClass::create([
            'item_id'           => $itemId,
            'from_warehouse_id' => $this->id,
            'to_warehouse_id'   => $toWarehouseId,
            'quantity'          => $quantity,
            'action'            => Warehouse::ACTION_MOVE
        ]);
    }

    /**
     * Sets the quantity $qty to the item at warehouse, the previous data will not be taken in account,
     * if item doesn't exist on that warehouse it will be created
     */
    public function setInventory(int $itemId, float $quantity, ?int $unitId = null)
    {
        if (! $unitId) {
            $itemClass = config('mojito.itemClass', 'Item');
            $unitId = $itemClass::find($itemId)->unit_id;
        }
        static::updateStock($itemId, $this->id, $quantity, $unitId, true);
        $stockMovementClass = config('mojito.stockMovementClass', 'StockMovement');
        return $stockMovementClass::create([
            'item_id'           => $itemId,
            'to_warehouse_id'   => $this->id,
            'quantity'          => $quantity,
            'action'            => Warehouse::ACTION_SET_INVENTORY
        ]);
    }

    protected static function updateStock(int $itemId, int $warehouseId, float $quantity, int $unitId, bool $shouldSet = false)
    {
        $stockClass = config('mojito.stockClass', 'Stock');
        if (! $stock = $stockClass::where('warehouse_id', '=', $warehouseId)->where('item_id', $itemId)->first()) {
            return $stockClass::create([
                'warehouse_id' => $warehouseId,
                'item_id'      => $itemId,
                'quantity'     => $quantity,
                'unit_id'      => $unitId,
                'alert'        => 0,
            ]);
        }
        $stock->update([
            "quantity" => $shouldSet ? $quantity : ($stock->quantity + Unit::convert($quantity, $unitId, $stock->unit_id))
        ]);
        return $stock;
    }
}

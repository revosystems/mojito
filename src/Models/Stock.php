<?php namespace BadChoice\Mojito\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use BadChoice\Grog\Traits\SyncTrait;

class Stock extends \Eloquent{

    use SoftDeletes;
    use SyncTrait;

    protected $dates            = ['deleted_at'];
    protected $hidden           = ['created_at','updated_at','deleted_at'];
    protected $guarded          = [];

    protected static $rules = [
        'alert'     => 'integer',
        'quantity'  => 'numeric'
    ];

    public static function findWith($item_id,$warehouse_id){
        return static::where('item_id',$item_id)
            ->where('warehouse_id',$warehouse_id)
            ->first();
    }

    //============================================================================
    // RELATIONSHIPS
    //============================================================================
    public function warehouse(){
        return $this->belongsTo(config('mojito.warehouseClass','Item'),'warehouse_id');
    }

    public function item(){
        return $this->belongsTo(config('mojito.itemClass','Item'),'item_id');
    }

    public function unit(){
        return $this->belongsTo('BadChoice\Mojito\Models\Unit');
    }

    //============================================================================
    // SCOPES
    //============================================================================
    public function scopeByWarehouse($query,$id){
        return $query->where('warehouse_id','=',$id);
    }

    public function scopeInventoryAlert($query){
        return $query->whereRaw('alert >= quantity');
    }

    public function scopeByItem($query,$id){
        return $query->where('item_id','=',$id);
    }

    //============================================================================
    // METHODS
    //============================================================================
    public static function softDelete($item_id, $warehouse_id){
        static::findWith($item_id,$warehouse_id)->delete();
    }
}
<?php namespace BadChoice\Mojito\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class Assembly extends \Eloquent {

    use SoftDeletes;

    protected $table    = "assemblies";
    protected $dates    = ['deleted_at'];
    protected $hidden   = ['created_at','updated_at','deleted_at'];
    protected $guarded  = [];

    //============================================================================
    // SCOPES
    //============================================================================
    public function scopeByMainItem($query,$id){
        return $query->where('main_item_id','=',$id);
    }

    public function scopeByItem($query,$id){
        return $query->where('item_id','=',$id);
    }

    public function scopeByUnit($query,$id){
        return $query->where('unit_id','=',$id);
    }

    //============================================================================
    // SOFT DELETE
    //============================================================================
    public static function softDelete($mainItemId,$itemId){
        self::where('main_item_id',$mainItemId)
            ->where('item_id',$itemId)
            ->first()->delete();
    }
}
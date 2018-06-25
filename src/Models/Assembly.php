<?php

namespace BadChoice\Mojito\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class Assembly extends Pivot
{
    protected $table    = "assemblies";
    protected $dates    = ['deleted_at'];
    protected $hidden   = ['created_at','updated_at','deleted_at'];
    protected $guarded  = [];

    public function __construct()
    {
        $this->table = config('mojito.assembliesTable');
    }

    public static function getTableName()
    {
        return with(new static)->getTable();
    }

    //*** Scopes don't work this way? **///
    /*public function __construct(Model $parent, $attributes, $table, $exists = false){
        parent::__construct($parent, $attributes, $table, $exists);
        $this->table = config('mojito.assembliesTable');
    }*/

    //============================================================================
    // RELATIONSHIPS
    //============================================================================
    public function item()
    {
        return $this->belongsTo(config('mojito.itemClass'));
    }

    public function mainItem()
    {
        return $this->belongsTo(config('mojito.itemClass'), 'main_item_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    //============================================================================
    // SCOPES
    //============================================================================
    public function scopeByMainItem($query, $id)
    {
        return $query->where('main_item_id', '=', $id);
    }

    public function scopeByItem($query, $id)
    {
        return $query->where('item_id', '=', $id);
    }

    public function scopeByUnit($query, $id)
    {
        return $query->where('unit_id', '=', $id);
    }
}

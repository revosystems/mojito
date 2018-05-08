<?php

namespace BadChoice\Mojito\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorItemPivot extends Model
{
    use SoftDeletes;

    protected $dates        = ['deleted_at'];
    protected $hidden       = ['created_at','updated_at','deleted_at'];
    protected $guarded      = ['id'];

    protected static $rules = [
        'costPrice'     => 'required|numeric',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('mojito.vendorItemsTable');
    }

    public static function canBeDeleted($id)
    {
        return true;
    }

    //============================================================================
    // SCOPES
    //============================================================================
    public function scopeByVendor($query, $id)
    {
        return $query->where('vendor_id', '=', $id);
    }

    public function scopeByItem($query, $id)
    {
        return $query->where('item_id', '=', $id);
    }

    public function scopeByUnit($query, $id)
    {
        return $query->where('unit_id', '=', $id);
    }

    //============================================================================
    // RELATIONSHIPS
    //============================================================================
    public function item()
    {
        return $this->belongsTo(config('mojito.itemClass', 'Item'), 'item_id')->withTrashed();
    }

    public function vendor()
    {
        return $this->belongsTo('BadChoice\Mojito\Models\Vendor', 'vendor_id');
    }

    public function unit()
    {
        return $this->belongsTo('BadChoice\Mojito\Models\Unit', 'unit_id');
    }
}

<?php

namespace BadChoice\Mojito\Models;

use BadChoice\Mojito\Services\PurchaseOrders\ItemsForAVendorPurchaseOrder;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

class Vendor extends Model
{
    use SoftDeletes;
    use Notifiable;
    protected $table   = 'vendors';
    protected $hidden  = ['created_at', 'updated_at', 'deleted_at'];
    protected $guarded = [];

    protected static $rules = [
        'name'       => 'required|min:3',
        'address'    => 'required|min:3',
        'city'       => 'min:3',
        'state'      => 'min:3',
        'country'    => 'min:3',
        'postalCode' => 'min:3',
        //'nif'           => 'required|between:9,9',
        'nif'   => 'required|min:3',
        'email' => 'email',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    //============================================================================
    // PARENT FUNCTIONS
    //============================================================================
    public static function canBeDeleted($id)
    {
        if (count(self::find($id)->orders) > 0) {
            throw new \Exception('Vendor has orders');
        }
        return true;
    }

    //============================================================================
    // RELATIONSHIPS
    //============================================================================
    public function items()
    {
        return $this->belongsToMany(config('mojito.itemClass', 'Item'), config('mojito.vendorItemsTable'), 'vendor_id', 'item_id')
            ->withPivot('id', 'costPrice', 'unit_id', 'reference', 'tax_id', 'pack')
            ->withTimestamps()
            ->wherePivotNull('deleted_at')
            ->using(config('mojito.vendorItemClass', 'VendorItemPivot'));
    }

    public function orders()
    {
        return $this->hasMany('BadChoice\Mojito\Models\PurchaseOrder');
    }

    //============================================================================
    // MEHTODS
    //============================================================================
    public function addItem($item_id, $unit_id)
    {
        $this->items()->attach($item_id, [
            'unit_id' => $unit_id,
            'pack'    => 1,
        ]);
    }

    public function automaticPurchaseOrder($dontValidateBelowAlert = true)
    {
        return (new ItemsForAVendorPurchaseOrder($this, !$dontValidateBelowAlert))->get();
    }

    public function delete()
    {
        foreach (VendorItemPivot::byVendor($this->id)->get() as $object) {
            $object->delete();
        }
        return parent::delete();
    }
}

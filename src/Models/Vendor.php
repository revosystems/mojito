<?php

namespace BadChoice\Mojito\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    use SoftDeletes;
    protected $table        = "vendors";
    protected $hidden       = ['created_at','updated_at','deleted_at'];
    protected $guarded      = ['id'];

    protected static $rules = [
        'name'          => 'required|min:3',
        'address'       => 'required|min:3',
        'city'          => 'min:3',
        'state'         => 'min:3',
        'country'       => 'min:3',
        'postalCode'    => 'min:3',
        //'nif'           => 'required|between:9,9',
        'nif'           => 'required|min:3',
        'email'         => 'email',
    ];

    //============================================================================
    // PARENT FUNCTIONS
    //============================================================================
    public static function canBeDeleted($id)
    {
        if (count(Vendor::find($id)->orders) > 0) {
            throw new \Exception("Vendor has orders");
        }
        return true;
    }

    //============================================================================
    // RELATIONSHIPS
    //============================================================================
    public function items()
    {
        return $this->belongsToMany(config('mojito.itemClass', 'Item'), config('mojito.vendorItemsTable'), 'vendor_id', 'item_id')->withPivot('id', 'costPrice', 'unit_id', 'reference', 'tax_id', 'pack')->wherePivot('deleted_at', '=', null);
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
            "unit_id" => $unit_id,
            "pack"    => 1,
        ]);
    }

    public function automaticPurchaseOrder($belowAlert = true)
    {
        $toReturn = [];
        foreach ($this->items as $item) {
            $totalQty       = 0;
            $totalDefault   = 0;
            $totalAlert     = 0;
            foreach ($item->warehouses as $warehouse) {
                $totalQty += $warehouse->pivot->quantity;
                $totalDefault += $warehouse->pivot->defaultQuantity;
                $totalAlert += $warehouse->pivot->alert;
            }

            $toRefill = $totalDefault - $totalQty;

            if ($toRefill <= 0 || $totalDefault == 0) {
                continue;
            }

            if (! $belowAlert && $totalQty > $totalAlert) {
                continue;
            }

            $toRefill = $item->pivot->pack * ceil($toRefill / $item->pivot->pack);  //Minium pack size

            $toReturn[$item->id] = [
                "name"      => $item->name,
                "costPrice" => $item->pivot->costPrice,
                "pivot_id"  => $item->pivot->id,
                "quantity"  => $toRefill,
                "pack"      => $item->pivot->pack,
                "unit"      => Unit::find($item->pivot->unit_id)->name,
            ];
        }
        return $toReturn;
    }

    public function delete()
    {
        foreach (VendorItemPivot::byVendor($this->id)->get() as $object) {
            $object->delete();
        }
        return parent::delete();
    }
}

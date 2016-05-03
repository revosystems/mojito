<?php namespace BadChoice\Mojito\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends \Eloquent {
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
    public static function canBeDeleted($id){
        return true;
    }

    //============================================================================
    // RELATIONSHIPS
    //============================================================================
    public function items(){
        //TODO: Vendor items table?
        return $this->belongsToMany(config('mojito.itemClass','Item'),'menu_item_vendor','vendor_id','item_id')->withPivot('id','costPrice','unit_id','reference','tax_id','pack')->wherePivot('deleted_at','=',null);
    }

    //============================================================================
    // MEHTODS
    //============================================================================
    public function automaticPurchaseOrder(){
        $toReturn = [];
        foreach($this->items as $item){

            $totalQty       = 0;
            $totalDefault   = 0;

            foreach($item->warehouses as $warehouse){
                $totalQty     += $warehouse->pivot->quantity;
                $totalDefault += $warehouse->pivot->defaultQuantity;
            }

            $toRefill = $totalDefault - $totalQty;

            if($toRefill > 0){

                $toRefill = $item->pivot->pack*ceil($toRefill/$item->pivot->pack);  //Minium pack size

                $toReturn[$item->id] = [
                    "name"      => $item->name,
                    "costPrice" => $item->pivot->costPrice,
                    "pivot_id"  => $item->pivot->id,
                    "quantity"  => $toRefill,
                    "unit"      => Unit::find($item->pivot->unit_id)->name,
                ];
            }
        }
        return $toReturn;
    }

    public function delete(){

        foreach (VendorItemPivot::byVendor($this->id)->get() as $object){
            $object->delete();
        }

        return parent::delete();
    }

}
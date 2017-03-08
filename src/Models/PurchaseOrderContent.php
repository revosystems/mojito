<?php namespace BadChoice\Mojito\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrderContent extends Model {
    use SoftDeletes;

    protected $table    = "purchase_order_contents";
    protected $guarded  = ['id'];
    protected $appends  = ['itemName','itemBarcode'];
    protected $hidden   = ['item','vendorItem'];

    const STATUS_PENDING            = 0;
    const STATUS_SENT               = 1;
    const STATUS_PARTIAL_RECEIVED   = 2;
    const STATUS_RECEIVED           = 3;

    //============================================================================
    // REGISTER EVENT LISTENRES
    //============================================================================
    public static function boot(){
        parent::boot();
        static::saved(function($purchaseOrderContent)
        {
            $po = PurchaseOrder::find($purchaseOrderContent->order_id);
            $po->update([
                "total"     => $po->calculateTotal(),
                "status"    => $po->calculateStatus(),
            ]);
        });
    }

    //============================================================================
    // RELATIONSHIPS
    //============================================================================
    public function order(){
        return $this->belongsTo(PurchaseOrder::class,'order_id');
    }

    public function vendor(){
        return $this->vendorItem->vendor;
    }

    public function item(){
        return $this->vendorItem->item;
    }

    public function vendorItem(){
        return $this->belongsTo(VendorItemPivot::class,'item_vendor_id');
    }

    //============================================================================
    // JSON APPENDS
    //============================================================================
    public function getItemNameAttribute(){
        return $this->vendorItem->item->name;
    }
    public function getItemBarcodeAttribute(){
        return $this->vendorItem->item->barcode;
    }


    //============================================================================
    // METHODS
    //============================================================================
    public function receive($quantity,$warehouseId){

        $warehouse  = Warehouse::find($warehouseId);
        $warehouse->add($this->item()->id, $quantity, $this->vendorItem->unit_id);

        $totalReceived = $this->received + $quantity;
        $status        = static::STATUS_PENDING;

        if($totalReceived < $this->quantity) $status = static::STATUS_PARTIAL_RECEIVED;
        if($totalReceived >= $this->quantity) $status = static::STATUS_RECEIVED;

        $this->update([
            'received' => $totalReceived,
            'status'   => $status,
        ]);
    }

    public function statusName(){
        return static::getStatusName($this->status);
    }

    public static function getStatusName($status){
        if($status      == static::STATUS_PENDING)          return trans('admin.pending');
        else if($status == static::STATUS_SENT)             return trans('admin.sent');
        else if($status == static::STATUS_PARTIAL_RECEIVED) return trans('admin.partialReceived');
        else if($status == static::STATUS_RECEIVED)         return trans('admin.received');
        return "?";
    }

    public static function statusArray(){
        return [
            static::STATUS_PENDING              => trans('admin.pending'),
            static::STATUS_SENT                 => trans('admin.sent'),
            static::STATUS_PARTIAL_RECEIVED     => trans('admin.partialReceived'),
            static::STATUS_RECEIVED             => trans('admin.received'),
        ];
    }
}
<?php

namespace BadChoice\Mojito\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrderContent extends Model
{
    use SoftDeletes;

    protected $table    = "purchase_order_contents";
    protected $guarded  = ['id'];
    protected $appends  = ['itemName', 'itemBarcode'];
    protected $hidden   = ['item', 'vendorItem'];

    const STATUS_PENDING            = 0;
    const STATUS_SENT               = 1;
    const STATUS_PARTIAL_RECEIVED   = 2;
    const STATUS_RECEIVED           = 3;
    const STATUS_DRAFT              = 4;

    //============================================================================
    // REGISTER EVENT LISTENRES
    //============================================================================
    public static function boot()
    {
        parent::boot();
        static::saved(function ($purchaseOrderContent) {
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
    public function order()
    {
        return $this->belongsTo(PurchaseOrder::class, 'order_id');
    }

    public function vendor()
    {
        return $this->vendorItem->vendor;
    }

    public function item()
    {
        return $this->vendorItem->item;
    }

    public function vendorItem()
    {
        return $this->belongsTo(VendorItemPivot::class, 'item_vendor_id')->withTrashed();
    }

    //============================================================================
    // JSON APPENDS
    //============================================================================
    public function getItemNameAttribute()
    {
        return $this->vendorItem->item->name ?? "";
    }

    public function getItemBarcodeAttribute()
    {
        return $this->vendorItem->item->barcode ?? "";
    }

    //============================================================================
    // METHODS
    //============================================================================
    public function receive($quantity, $warehouseId)
    {
        $warehouse  = Warehouse::find($warehouseId);
        $warehouse->add($this->vendorItem->item_id, $quantity, $this->vendorItem->unit_id);

        $totalReceived = $this->received + $quantity;
        $status        = static::STATUS_PENDING;

        if ($totalReceived < $this->quantity) {
            $status = static::STATUS_PARTIAL_RECEIVED;
        }
        if ($totalReceived >= $this->quantity) {
            $status = static::STATUS_RECEIVED;
        }

        $this->update([
            'received' => $totalReceived,
            'status'   => $status,
        ]);
    }

    public function statusName()
    {
        return static::getStatusName($this->status);
    }

    public static function getStatusName($status)
    {
        if ($status == static::STATUS_PENDING) {
            return __('admin.pending');
        } elseif ($status == static::STATUS_SENT) {
            return __('admin.sent');
        } elseif ($status == static::STATUS_PARTIAL_RECEIVED) {
            return __('admin.partialReceived');
        } elseif ($status == static::STATUS_RECEIVED) {
            return __('admin.received');
        } elseif ($status == static::STATUS_DRAFT) {
            return __('admin.draft');
        }
        return "?";
    }

    public static function statusArray()
    {
        return [
            static::STATUS_PENDING              => __('admin.pending'),
            static::STATUS_SENT                 => __('admin.sent'),
            static::STATUS_PARTIAL_RECEIVED     => __('admin.partialReceived'),
            static::STATUS_RECEIVED             => __('admin.received'),
            static::STATUS_DRAFT                => __('admin.draft'),
        ];
    }

    public function updatePrice($price)
    {
        $this->update(["price" => str_replace(',', '.', $price)]);
    }

    public function updateQuantity($quantity, $warehouseId)
    {
        $leftToReceive      = $quantity - $this->received;
        $this->quantity     = $quantity;
        $this->received     = $leftToReceive < 0 ? $quantity : $this->received;
        $this->status       = $this->calculateStatus();
        $this->adjustStock($leftToReceive, $warehouseId);
        $this->save();
    }

    public function calculateStatus()
    {
        $leftToReceive  = $this->quantity - $this->received;
        if ($this->status == PurchaseOrderContent::STATUS_DRAFT) {
            return PurchaseOrderContent::STATUS_DRAFT;
        } elseif ($leftToReceive == 0) {
            return PurchaseOrderContent::STATUS_RECEIVED;
        } elseif ($leftToReceive == $this->quantity) {
            return PurchaseOrderContent::STATUS_PENDING;
        }
        return PurchaseOrderContent::STATUS_PARTIAL_RECEIVED;
    }

    private function adjustStock($leftToReceive, $warehouseId)
    {
        if ($leftToReceive >= 0) {
            return;
        }
        Warehouse::find($warehouseId)->add($this->vendorItem->item_id, $leftToReceive, $this->vendorItem->unit_id);
    }
}

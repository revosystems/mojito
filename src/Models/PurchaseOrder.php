<?php

namespace BadChoice\Mojito\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use SoftDeletes;

    protected $table    = "purchase_orders";
    protected $guarded  = ['id'];
    protected $appends  = ['vendorName', 'contentsArray'];
    protected $hidden   = ['vendor', 'contents'];

    public static function canBeDeleted($id)
    {
        return true;
    }

    public static function createWith($vendor_id, $items, $status = PurchaseOrderContent::STATUS_PENDING)
    {
        if (! count($items)) {
            return null;
        }

        $order = tap(self::create(compact('vendor_id', 'status')), function ($order) use ($items) {
            return $order->contents()->createMany(collect($items)->map(function ($item) use ($order) {
                return (new PurchaseOrderContent([
                    'status'         => $order->status,
                    'price'          => $item->costPrice,
                    'quantity'       => $item->quantity,
                    'item_vendor_id' => $item->pivot_id,
                ]))->makeHidden(['itemName', 'itemBarcode']);
            })->toArray());
        });
        if ($order->shouldBeSent()) {
            $order->send();
        }
        return $order;
    }

    public static function updateWith($order, $items, $status = PurchaseOrderContent::STATUS_PENDING)
    {
        if (! count($items)) {
            return null;
        }

        $order->update(compact("status"));
        $order->contents()->whereNotIn('id', collect($items)->pluck('id'))->delete();
        collect($items)->each(function ($item) use ($order) {
            PurchaseOrderContent::updateOrCreate([
                'id'             => $item->id,
                'order_id'       => $order->id,
                'item_vendor_id' => $item->pivot_id,
            ], [
                'status'   => $order->status,
                'price'    => $item->costPrice,
                'quantity' => $item->quantity,
            ]);
        });
        if ($order->shouldBeSent()) {
            $order->send();
        }
        return $order;
    }

    public function delete()
    {
        $this->contents()->delete();
        return parent::delete();
    }

    //============================================================================
    // RELATIONSHIPS
    //============================================================================
    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function contents()
    {
        return $this->hasMany(PurchaseOrderContent::class, 'order_id');
    }

    //============================================================================
    // SCOPES
    //============================================================================
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', '=', $status);
    }

    public function scopeActive($query)
    {
        return $query->where('status', '=', PurchaseOrderContent::STATUS_PENDING)
                     ->orWhere('status', '=', PurchaseOrderContent::STATUS_PARTIAL_RECEIVED);
    }

    //============================================================================
    // JSON ATTRIBUTES
    //============================================================================
    public function getVendorNameAttribute()
    {
        return ($this->vendor) ? $this->vendor->name : "Vendor Deleted";
    }

    public function getContentsArrayAttribute()
    {
        return $this->contents;
    }

    //============================================================================
    // METHODS
    //============================================================================

    /**
     * Called for purchaseOrderContent, when it is updated, it updates the orderStatus
     * @return int
     */
    public function calculateTotal()
    {
        return $this->contents->sum(function ($content) {
            return $content->quantity * $content->price;
        });
    }

    public function calculateTax(){
        return $this->contents->sum(function ($content) {
            return $content->price * $content->quantity * $content->item->taxToUse()->percentage / 100;
        });
    }

    /**
     * Called for purchaseOrderContent, when it is updated, it updates the orderStatus
     * @return int
     */
    public function calculateStatus()
    {
        $total          = $this->contents->sum('quantity');
        $received       = $this->contents->sum('received');
        $leftToReceive  = $total - $received;

        if ($this->status == PurchaseOrderContent::STATUS_DRAFT) {
            return PurchaseOrderContent::STATUS_DRAFT;
        } elseif ($leftToReceive <= 0) {
            return PurchaseOrderContent::STATUS_RECEIVED;
        } elseif ($leftToReceive == $total) {
            return PurchaseOrderContent::STATUS_PENDING;
        }
        return PurchaseOrderContent::STATUS_PARTIAL_RECEIVED;
    }

    public function statusName()
    {
        return PurchaseOrderContent::getStatusName($this->status);
    }

    public function receiveAll($warehouse_id)
    {
        $this->contents->each(function ($content) use ($warehouse_id) {
            $content->receive($content->quantity - $content->received, $warehouse_id);
        });
    }

    public function shouldBeSent()
    {
        return $this->status == PurchaseOrderContent::STATUS_PENDING;
    }

    public function send()
    {
    }
}

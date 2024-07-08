<?php

namespace BadChoice\Mojito\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use BadChoice\Mojito\Enums\PurchaseOrderStatus;
use Carbon\Carbon;

class PurchaseOrder extends Model
{
    use SoftDeletes;

    protected $table    = "purchase_orders";
    protected $guarded  = [];
    protected $appends  = ['vendorName', 'contentsArray'];
    protected $hidden   = ['vendor', 'contents'];

    protected $casts = [
        'status' => PurchaseOrderStatus::class,
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public static function canBeDeleted($id)
    {
        return true;
    }

    public static function createWith($vendor_id, $items, $status = PurchaseOrderStatus::STATUS_PENDING)
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
                ]))->makeHidden(['itemName', 'itemBarcode', 'item_id']);
            })->toArray());
        });
        if ($order->shouldBeSent()) {
            $order->send();
        }
        return $order;
    }

    public static function updateWith($order, $items, $status = PurchaseOrderStatus::STATUS_PENDING)
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

    public static function updateOrderInfo(object $orderData) {
        $order = PurchaseOrder::find($orderData->id);
        $update = [
            'created_at' => $orderData->created_at ?? $order->created_at,
        ];

        if(isset($order->reference)){
            $update['reference'] = $orderData->reference ?? $order->reference;
        }

        $order->update($update);

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
        return $this->belongsTo(config('mojito.vendorClass', Vendor::class));
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
        return $query->where(fn ($q) =>
            $q->where('status', '=', PurchaseOrderStatus::STATUS_PENDING)
                ->orWhere('status', '=', PurchaseOrderStatus::STATUS_PARTIAL_RECEIVED)
        );
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
    public function calculateSubtotal()
    {
        return $this->contents->sum(function ($content) {
            return $content->quantity * $content->price;
        });
    }

    public function calculateTax()
    {
        return $this->contents()->with('vendorItem.tax')->get()->sum(function ($content) {
            return $content->quantity * $content->price * ($content->vendorItem->tax->percentage ?? 0) / 100.0;
        });
    }

    public function calculateTotal()
    {
        return $this->calculateSubtotal() + $this->calculateTax();
    }

    /**
     * Called for purchaseOrderContent, when it is updated, it updates the orderStatus
     * @return int
     */
    public function calculateStatus()
    {
        $total          = $this->contents->sum('quantity');
        $leftToReceive  = $this->contents->sum(function ($content) {
            $leftToReceive = $content->quantity - $content->received;
            return $leftToReceive < 0 ? 0 : $leftToReceive;
        });
        if ($this->status === PurchaseOrderStatus::STATUS_DRAFT) {
            return PurchaseOrderStatus::STATUS_DRAFT;
        } elseif ($leftToReceive <= 0) {
            return PurchaseOrderStatus::STATUS_RECEIVED;
        } elseif ($leftToReceive == $total) {
            return PurchaseOrderStatus::STATUS_PENDING;
        }
        return PurchaseOrderStatus::STATUS_PARTIAL_RECEIVED;
    }

    public function receiveAll($warehouse_id)
    {
        $this->contents()->with('vendorItem', 'order')->get()->each(function ($content) use ($warehouse_id) {
            $content->receive($content->quantity - $content->received, $warehouse_id);
        });
    }

    public function shouldBeSent()
    {
        return $this->status === PurchaseOrderStatus::STATUS_PENDING;
    }

    public function send()
    {
    }
}

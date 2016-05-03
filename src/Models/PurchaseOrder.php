<?php namespace BadChoice\Mojito\Models;

use GSModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends \Eloquent {
    use SoftDeletes;

    protected $table    = "purchase_orders";
    protected $guarded  = ['id'];

    //============================================================================
    // RELATIONSHIPS
    //============================================================================
    public function vendor(){
        return $this->belongsTo('BadChoice\Mojito\Models\Vendor');
    }

    public function contents(){
        return $this->hasMany('BadChoice\Mojito\Models\PurchaseOrderContent','order_id');
    }

    //============================================================================
    // SCOPES
    //============================================================================
    public function scopeByStatus($query,$status){
        return $query->where('status','=',$status);
    }

    //============================================================================
    // METHODS
    //============================================================================
    /**
     * Called for purchaseOrderContent, when it is updated, it updates the orderStatus
     * @return int
     */
    public function calculateTotal(){
        $total = 0;
        foreach($this->contents as $content){
            $total += $content->quantity*$content->price;
        }
        return $total;
    }

    /**
     * Called for purchaseOrderContent, when it is updated, it updates the orderStatus
     * @return int
     */
    public function calculateStatus(){
        $total          = $this->contents->sum('quantity');
        $received       = $this->contents->sum('received');
        $leftToReceive  = $total - $received;

        if ($leftToReceive == 0)            return PurchaseOrderContent::STATUS_RECEIVED;
        else if ($leftToReceive == $total)  return PurchaseOrderContent::STATUS_PENDING;
        else                                return PurchaseOrderContent::STATUS_PARTIAL_RECEIVED;
    }

    public function statusName(){
        return PurchaseOrderContent::getStatusName($this->status);
    }

    public function receiveAll($warehouse_id){
        foreach($this->contents as $content){
            $content->receive($content->quantity - $content->received, $content->vendorItem->pack,$warehouse_id);
        }
    }

}
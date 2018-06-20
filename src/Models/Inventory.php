<?php

namespace BadChoice\Mojito\Models;

use BadChoice\Grog\Traits\SaveNestedTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inventory extends Model
{
    const STATUS_OPENED     = 1;
    const STATUS_PENDING    = 2;
    const STATUS_APPROVED   = 3;
    const STATUS_DENIED     = 4;

    protected $guarded = [];

    use SoftDeletes;
    use SaveNestedTrait;

    public function warehouse()
    {
        return $this->belongsTo(config('mojito.warehouseClass', 'Warehouse'), 'warehouse_id');
    }

    public function contents()
    {
        return $this->hasMany(config('mojito.inventoryContentClass', 'InventoryContent'));
    }

    public function scopeApproved($query)
    {
        return $query->where('status', static::STATUS_APPROVED);
    }

    public function updateStatus($status)
    {
        if ($this->status == static::STATUS_APPROVED || $this->status == static::STATUS_DENIED){
            return;
        }
        if ($status == static::STATUS_APPROVED) {
            $this->approve();
        }
        $this->status = $status;
    }
    
    public function approve()
    {
        $this->contents()->each(function (InventoryContent $content) {
            $content->approve();
        });
    }
}

<?php

namespace BadChoice\Mojito\Models;

use BadChoice\Grog\Traits\SaveNestedTrait;
use BadChoice\Mojito\Exceptions\AlreadyApprovedException;
use BadChoice\Mojito\Exceptions\AlreadyDeniedException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Mockery\Exception;

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

    public function employee()
    {
        return $this->belongsTo(config('mojito.employeeClass', 'Employee'), 'employee_id');
    }

    public function contents()
    {
        return $this->hasMany(config('mojito.inventoryContentClass', 'InventoryContent'));
    }

    public function availableStatus()
    {
        return [
            static::STATUS_OPENED     => __('admin.opened'),
            static::STATUS_PENDING    => __('admin.pending'),
            static::STATUS_APPROVED   => __('admin.approved'),
            static::STATUS_DENIED     => __('admin.denied'),
        ];
    }

    public function statusName()
    {
        return $this->availableStatus()[$this->status];
    }

    public function scopeApproved($query)
    {
        return $query->where('status', static::STATUS_APPROVED);
    }

    public function approve()
    {
        $this->canUpdateStatus();
        $this->contents->each->approve();
        $this->update(["status" => static::STATUS_APPROVED]);
    }

    public function deny()
    {
        $this->canUpdateStatus();
        $this->update(["status" => static::STATUS_DENIED]);
    }

    protected function canUpdateStatus() {
        if ($this->status == static::STATUS_APPROVED) {
            throw new AlreadyApprovedException;
        }
        if ($this->status == static::STATUS_DENIED) {
            throw new AlreadyDeniedException();
        }
    }
}

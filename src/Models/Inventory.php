<?php

namespace BadChoice\Mojito\Models;

use BadChoice\Grog\Traits\SaveNestedTrait;
use BadChoice\Mojito\Exceptions\AlreadyApprovedException;
use BadChoice\Mojito\Exceptions\AlreadyDeclinedException;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inventory extends Model
{
    const STATUS_OPENED     = 1;
    const STATUS_PENDING    = 2;
    const STATUS_APPROVED   = 3;
    const STATUS_DECLINED   = 4;

    protected $dates   = ["closed_at"];
    protected $guarded = [];

    use SoftDeletes;
    use SaveNestedTrait;

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function warehouse()
    {
        return $this->belongsTo(config('mojito.warehouseClass', 'Warehouse'), 'warehouse_id');
    }

    public function employee()
    {
        return $this->belongsTo(config('mojito.employeeClass', 'Employee'), 'employee_id')->withTrashed();
    }

    public function contents()
    {
        return $this->hasMany(config('mojito.inventoryContentClass', 'InventoryContent'));
    }

    public static function availableStatus()
    {
        return [
            static::STATUS_OPENED   => __('admin.opened'),
            static::STATUS_PENDING  => __('admin.pending'),
            static::STATUS_APPROVED => __('admin.approved'),
            static::STATUS_DECLINED => __('admin.declined'),
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

    public function getPreviousApprovedInventory()
    {
        return $this->approved()->where('closed_at', "<", $this->closed_at ?: $this->created_at)->latest()->first();
    }

    public function approve()
    {
        $this->validateCanUpdateStatus();
        $this->contents->each->approve();
        $this->update(["status" => static::STATUS_APPROVED]);
    }

    public function decline()
    {
        $this->validateCanUpdateStatus();
        $this->update(["status" => static::STATUS_DECLINED]);
    }

    protected function validateCanUpdateStatus()
    {
        if ($this->status == static::STATUS_APPROVED) {
            throw new AlreadyApprovedException;
        }
        if ($this->status == static::STATUS_DECLINED) {
            throw new AlreadyDeclinedException();
        }
    }
}

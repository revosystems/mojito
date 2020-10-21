<?php

namespace BadChoice\Mojito\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $table            = "stock_movements";
    protected $guarded          = [];

    const SOURCE_SALE           = 0;
    const SOURCE_REVO_STOCKS    = 1;
    const SOURCE_PURCHASE       = 2;
    const SOURCE_BACK           = 3;

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    //============================================================================
    // RELATIONSHIPS
    //============================================================================
    public function item()
    {
        return $this->belongsTo(config('mojito.itemClass', 'Item'), 'item_id')->withTrashed();
    }

    public function employee()
    {
        return $this->belongsTo(config('mojito.employeeClass', 'Item'), 'tenantUser_id')->withTrashed();
    }

    public function fromWarehouse()
    {
        return $this->belongsTo(config('mojito.warehouseClass'), 'from_warehouse_id')->withTrashed();
    }

    public function toWarehouse()
    {
        return $this->belongsTo(config('mojito.warehouseClass'), 'to_warehouse_id')->withTrashed();
    }

    public static function getSourceName($source)
    {
        return [
            StockMovement::SOURCE_SALE          => __('mojito.sale'),
            StockMovement::SOURCE_REVO_STOCKS   => __('mojito.revoStocks'),
            StockMovement::SOURCE_PURCHASE      => __('mojito.purchase'),
            StockMovement::SOURCE_BACK          => __('mojito.back')
        ][$source] ?? '';
    }
}

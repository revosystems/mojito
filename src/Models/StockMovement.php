<?php

namespace BadChoice\Mojito\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $table            = "stock_movements";
    protected $guarded          = [];

    const SOURCE_SALE           = 0;
    const SOURCE_REVO_STOCKS    = 1;
    const SOURCE_PURCHASE       = 2;
    const SOURCE_BACK           = 3;

    //============================================================================
    // RELATIONSHIPS
    //============================================================================
    public function item()
    {
        return $this->belongsTo(config('mojito.itemClass', 'Item'), 'item_id');
    }

    public function employee()
    {
        return $this->belongsTo(config('mojito.employeeClass', 'Item'), 'tenantUser_id');
    }

    public function fromWarehouse()
    {
        return $this->belongsTo(config('mojito.warehouseClass'), 'from_warehouse_id');
    }

    public function toWarehouse()
    {
        return $this->belongsTo(config('mojito.warehouseClass'), 'to_warehouse_id');
    }

    public static function getSourceName($source)
    {
        if ($source == StockMovement::SOURCE_SALE) {
            return trans('mojito.sale');
        }
        if ($source == StockMovement::SOURCE_REVO_STOCKS) {
            return trans('mojito.revoStocks');
        }
        if ($source == StockMovement::SOURCE_PURCHASE) {
            return trans('mojito.purchase');
        }
        if ($source == StockMovement::SOURCE_BACK) {
            return trans('mojito.back');
        }
    }
}

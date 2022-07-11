<?php

namespace BadChoice\Mojito\Services\PurchaseOrders;

use BadChoice\Mojito\Models\Unit;
use BadChoice\Mojito\Models\Vendor;
use BadChoice\Mojito\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ItemsForAVendorPurchaseOrder
{
    public function __construct(protected Vendor $vendor, protected bool $validatesBelowAlert = false)
    {
    }

    public function get(Warehouse $warehouse = null): Collection
    {
        return $this->itemsWithMissingStock($warehouse)->mapWithKeys(function ($item) {
            return self::toArray($item, $item->stocks);
        });
    }

    protected function itemsWithMissingStock(?Warehouse $warehouse): \Illuminate\Database\Eloquent\Collection
    {
        return $this->vendor->items()
            /** TODO: Change to withWhereHas when it's available */
            ->whereHas('stocks', fn ($stocksQuery) => $this->stocksQuery($stocksQuery, $warehouse))
            ->with('stocks', fn ($stocksQuery) => $this->stocksQuery($stocksQuery, $warehouse))
            ->get();
    }

    public function stocksQuery(Builder|HasMany $stocksQuery, ?Warehouse $warehouse)
    {
        return $stocksQuery
            ->where('defaultQuantity', '>', 0)
            ->where('quantity', '<', DB::raw('defaultQuantity'))
            ->when($this->validatesBelowAlert, fn ($stocksQuery) => $stocksQuery->where('quantity', '<=', DB::raw('alert')))
            ->when($warehouse, fn ($stocksQuery) => $stocksQuery->where('warehouse_id', $warehouse->id));
    }

    public static function toArray($item, Collection $stocks): array
    {
        return [
            $item->id => [
                'name'      => $item->name,
                'costPrice' => $item->pivot->costPrice,
                'pivot_id'  => $item->pivot->id,
                'quantity'  => $item->pivot->pack * ceil($stocks->sum('defaultQuantity') - $stocks->sum('quantity') / $item->pivot->pack),
                'pack'      => $item->pivot->pack,
                'unit'      => Unit::find($item->pivot->unit_id)->name,
            ],
        ];
    }
}

<?php

namespace BadChoice\Mojito\Services\PurchaseOrders;

use BadChoice\Mojito\Models\Unit;
use BadChoice\Mojito\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ItemsForAVendorPurchaseOrder
{
    public function __construct(protected Vendor $vendor, protected bool $validatesBelowAlert = false)
    {
    }

    public function get(?Collection $warehouses = null): Collection
    {
        return $this->itemsWithMissingStock($warehouses)->mapWithKeys(function ($item) {
            return self::toArray($item, $item->stocks);
        });
    }

    protected function itemsWithMissingStock(?Collection $warehouses): \Illuminate\Database\Eloquent\Collection
    {
        return $this->vendor->items()
            /** TODO: Change to withWhereHas when it's available */
            ->whereHas('stocks', fn ($stocksQuery) => $this->stocksQuery($stocksQuery, $warehouses))
            ->with('stocks', fn ($stocksQuery) => $this->stocksQuery($stocksQuery, $warehouses))
            ->get();
    }

    public function stocksQuery(Builder|HasMany $stocksQuery, ?Collection $warehouses)
    {
        return $stocksQuery
            ->where('defaultQuantity', '>', 0)
            ->where('quantity', '<', DB::raw('defaultQuantity'))
            ->when($this->validatesBelowAlert, fn ($stocksQuery) => $stocksQuery->where('quantity', '<=', DB::raw('alert')))
            ->when($warehouses && $warehouses->isNotEmpty(), fn ($stocksQuery) => $stocksQuery->whereIn('warehouse_id', $warehouses->pluck('id')));
    }

    public static function toArray($item, Collection $stocks): array
    {
        return [
            $item->id => [
                'name'      => $item->name,
                'costPrice' => $item->pivot->costPrice,
                'pivot_id'  => $item->pivot->id,
                'quantity'  => $item->pivot->pack * ceil(($stocks->sum('defaultQuantity') - $stocks->sum('quantity')) / $item->pivot->pack),
                'pack'      => $item->pivot->pack,
                'unit'      => Unit::find($item->pivot->unit_id)->name,
            ],
        ];
    }
}

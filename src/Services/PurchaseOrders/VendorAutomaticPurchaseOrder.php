<?php

namespace BadChoice\Mojito\Services\PurchaseOrders;

use BadChoice\Mojito\Models\Unit;
use BadChoice\Mojito\Models\Vendor;
use BadChoice\Mojito\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VendorAutomaticPurchaseOrder
{
    public function __construct(protected Vendor $vendor)
    {
    }

    public function get(bool $belowAlert = true): Collection
    {
        $toReturn = [];
        foreach ($this->vendor->items as $item) {
            $totalQty     = 0;
            $totalDefault = 0;
            $totalAlert   = 0;
            foreach ($item->warehouses as $warehouse) {
                $totalQty += $warehouse->pivot->quantity;
                $totalDefault += $warehouse->pivot->defaultQuantity;
                $totalAlert += $warehouse->pivot->alert;
            }

            $toRefill = $totalDefault - $totalQty;

            if ($toRefill <= 0 || $totalDefault == 0) {
                continue;
            }

            if (! $belowAlert && $totalQty > $totalAlert) {
                continue;
            }

            $toRefill = $item->pivot->pack * ceil($toRefill / $item->pivot->pack);  //Minium pack size

            $toReturn[$item->id] = [
                'name'      => $item->name,
                'costPrice' => $item->pivot->costPrice,
                'pivot_id'  => $item->pivot->id,
                'quantity'  => $toRefill,
                'pack'      => $item->pivot->pack,
                'unit'      => Unit::find($item->pivot->unit_id)->name,
            ];
        }
        return collect($toReturn);
    }

    public function forWarehouse(Warehouse $warehouse): Collection
    {
        return $this->itemsWithStock($warehouse)->mapWithKeys(function ($item) {
            return [
                $item->id => [
                    'name'      => $item->name,
                    'costPrice' => $item->pivot->costPrice,
                    'pivot_id'  => $item->pivot->id,
                    'quantity'  => $item->pivot->pack * ceil($item->stocks->sum('defaultQuantity') - $item->stocks->sum('quantity') / $item->pivot->pack),
                    'pack'      => $item->pivot->pack,
                    'unit'      => Unit::find($item->pivot->unit_id)->name,
                ],
            ];
        });
    }

    protected function itemsWithStock(Warehouse $warehouse): \Illuminate\Database\Eloquent\Collection
    {
        return $this->vendor->items()
            /** TODO: Change to withWhereHas when it's available */
            ->whereHas('stocks', fn ($stocksQuery) => $this->stocksQueryByWarehouse(
                $stocksQuery,
                $warehouse
            ))
            ->with('stocks', fn ($stocksQuery) => $this->stocksQueryByWarehouse($stocksQuery, $warehouse))
            ->get();
    }

    protected function stocksQueryByWarehouse(Builder|HasMany $stocksQuery, Warehouse $warehouse)
    {
        return $stocksQuery
            ->where('warehouse_id', $warehouse->id)
            ->where('defaultQuantity', '>', 0)
            ->where('quantity', '<', DB::raw('defaultQuantity'));
    }
}

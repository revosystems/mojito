<?php

namespace BadChoice\Mojito\Services;

use BadChoice\Mojito\Models\PurchaseOrder;
use BadChoice\Mojito\Models\PurchaseOrderContent;
use BadChoice\Mojito\Models\VendorItemPivot;

class PurchaseOrderHandler
{
    protected $purchaseOrder;
    protected $warehouseId;
    protected $changes = [];

    public static function create($items, $vendorId)
    {
        $items = PurchaseOrderHandler::createVendorItemsIfNecessary($items, $vendorId)->map(function ($item) {
            return (object)[
                'costPrice' => $item->costPrice ?? 0,
                'quantity'  => $item->quantity,
                'pivot_id'  => VendorItemPivot::where('item_id', $item->id)->where('vendor_id', request('vendor'))->first()->id,
            ];
        });
        return PurchaseOrder::createWith($vendorId, $items);
    }

    public static function make($purchaseOrderId, $warehouseId)
    {
        $handler                = new static;
        $handler->purchaseOrder = PurchaseOrder::findOrFail($purchaseOrderId);
        $handler->warehouseId   = $warehouseId;
        return $handler;
    }

    public function updateContentsAndReceive($contents)
    {
        return $this->updateContents($contents, true);
    }

    public function updateContents($contents, $shouldReceive = false)
    {
        if (! $contents) {
            return $this;
        }
        list($existingReceived, $unExistingReceived) = collect($contents)->partition(function ($toReceive) {
            return $toReceive->content_id != null;
        });
        $this->updateExistingContents($existingReceived, $shouldReceive);
        $this->createUnExistingContents($unExistingReceived, $shouldReceive);
        return $this;
    }

    public function receiveAll()
    {
        $this->purchaseOrder->receiveAll($this->warehouseId);
        return $this;
    }

    private static function createVendorItemsIfNecessary($items, $vendorId) {
        return collect($items)->map(function ($item) use ($vendorId) {
            VendorItemPivot::firstOrCreate([
                "item_id"   => $item->id,
                "vendor_id" => $vendorId,
            ], [
                "unit_id" => 1,
                "pack"    => 1,
            ]);
            return $item;
        });
    }

    private function updateExistingContents($existingReceived, $shouldReceive)
    {
        collect($existingReceived)->each(function ($toReceive) use ($shouldReceive) {
            $purchaseOrderContent = PurchaseOrderContent::findOrFail($toReceive->content_id);
            $purchaseOrderContent->update(["quantity" => $toReceive->expectedQuantity]);
            if ($shouldReceive) {
                $purchaseOrderContent->receive($toReceive->toReceive, $this->warehouseId);
            }
        });
    }

    private function createUnExistingContents($unExistingReceived, $shouldReceive)
    {
        collect($unExistingReceived)->each(function ($toReceive) use ($shouldReceive) {
            $content = $this->createOrderContent($toReceive);
            if ($shouldReceive) {
                $content->receive($toReceive->toReceive, $this->warehouseId);
            }
        });
    }

    private function createOrderContent($toReceive)
    {
        return PurchaseOrderContent::create([
            "order_id"          => $this->purchaseOrder->id,
            "item_vendor_id"    => VendorItemPivot::firstOrCreate([
                "item_id"   => $toReceive->item_id,
                "vendor_id" => $this->purchaseOrder->vendor_id,
            ], [
                "unit_id" => 1,
                "pack"    => 1,
            ])->id,
            "quantity"          => $toReceive->expectedQuantity ?? 0,
            "status"            => PurchaseOrderContent::STATUS_PENDING
        ]);
    }
}

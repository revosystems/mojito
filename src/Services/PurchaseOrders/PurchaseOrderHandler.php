<?php

namespace BadChoice\Mojito\Services\PurchaseOrders;

use BadChoice\Mojito\Models\PurchaseOrder;
use BadChoice\Mojito\Models\PurchaseOrderContent;
use BadChoice\Mojito\Models\VendorItemPivot;

class PurchaseOrderHandler
{
    public $purchaseOrder;
    protected $warehouse;
    protected $logger;

    public function __construct()
    {
        $this->logger = new PurchaseOrderLogger();
    }

    public static function create($items, $vendorId)
    {
        $items = PurchaseOrderHandler::createVendorItemsIfNecessary($items, $vendorId)->map(function ($item) {
            $vendorItemClass = config('mojito.vendorItemClass');
            return (object)[
                'costPrice' => $item->costPrice ?? 0,
                'quantity'  => $item->quantity,
                'pivot_id'  => $vendorItemClass::where('item_id', $item->id)->where('vendor_id', request('vendor'))->first()->id,
            ];
        });
        return PurchaseOrder::createWith($vendorId, $items);
    }

    public static function make($purchaseOrderId, $warehouse)
    {
        $handler                = new static;
        $handler->purchaseOrder = PurchaseOrder::findOrFail($purchaseOrderId);
        $handler->warehouse     = $warehouse;
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
        $this->purchaseOrder->receiveAll($this->warehouse->id);
        $this->logger->fullyReceived($this->purchaseOrder);
        return $this;
    }

    public function getChanges()
    {
        return $this->logger->getChanges();
    }

    private static function createVendorItemsIfNecessary($items, $vendorId)
    {
        return collect($items)->map(function ($item) use ($vendorId) {
            $vendorItemClass = config('mojito.vendorItemClass');
            $vendorItemClass::firstOrCreate([
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
            if ($purchaseOrderContent->quantity != $toReceive->expectedQuantity) {
                $this->logger->contentsQuantityUpdated($purchaseOrderContent, $toReceive->expectedQuantity);
                $purchaseOrderContent->update(["quantity" => $toReceive->expectedQuantity]);
            }
            if ($shouldReceive) {
                $purchaseOrderContent->receive($toReceive->toReceive, $this->warehouse->id);
                $this->logger->contentsReceived($purchaseOrderContent, $this->warehouse);
            }
        });
    }

    private function createUnExistingContents($unExistingReceived, $shouldReceive)
    {
        collect($unExistingReceived)->each(function ($toReceive) use ($shouldReceive) {
            $content = $this->createOrderContent($toReceive);
            if ($shouldReceive) {
                $content->receive($toReceive->toReceive, $this->warehouse->id);
            }
        });
    }

    private function createOrderContent($toReceive)
    {
        return PurchaseOrderContent::create([
            "order_id"          => $this->purchaseOrder->id,
            "item_vendor_id"    => $this->findOrCreateVendor($toReceive)->id,
            "quantity"          => $toReceive->expectedQuantity,
            "status"            => PurchaseOrderContent::STATUS_PENDING
        ]);
    }

    private function findOrCreateVendor($toReceive)
    {
        $vendorItemClass = config('mojito.vendorItemClass');
        $vendorItem = $vendorItemClass::firstOrCreate([
            "item_id"   => $toReceive->item_id,
            "vendor_id" => $this->purchaseOrder->vendor_id,
        ], [
            "unit_id" => 1,
            "pack"    => 1,
        ]);
        if ($vendorItem->wasRecentlyCreated) {
            $this->logger->vendorItemCreated($vendorItem);
        }
        return $vendorItem;
    }
}

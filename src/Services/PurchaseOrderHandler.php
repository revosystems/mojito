<?php

namespace BadChoice\Mojito\Services;

use BadChoice\Mojito\Models\PurchaseOrder;
use BadChoice\Mojito\Models\PurchaseOrderContent;
use BadChoice\Mojito\Models\VendorItemPivot;
use Carbon\Carbon;

class PurchaseOrderHandler
{
    protected $purchaseOrder;
    protected $warehouse;
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

    public static function make($purchaseOrderId, $warehouse)
    {
        $handler                = new static;
        $handler->purchaseOrder = PurchaseOrder::findOrFail($purchaseOrderId);
        $handler->warehouse   = $warehouse;
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
        $this->logMessage('fullyReceived', [1 => $this->purchaseOrder->id, 2 => timeZoned(Carbon::now())]);
        return $this;
    }

    public function getChanges()
    {
        return $this->changes;
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
            if ($purchaseOrderContent->quantity != $toReceive->expectedQuantity) {
                $this->logContentsQuantityUpdated($purchaseOrderContent, $toReceive->expectedQuantity);
                $purchaseOrderContent->update(["quantity" => $toReceive->expectedQuantity]);
            }
            if ($shouldReceive) {
                $purchaseOrderContent->receive($toReceive->toReceive, $this->warehouse->id);
                $this->logContentsReceived($purchaseOrderContent);
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
        $vendorItem = VendorItemPivot::firstOrCreate([
            "item_id"   => $toReceive->item_id,
            "vendor_id" => $this->purchaseOrder->vendor_id,
        ], [
            "unit_id" => 1,
            "pack"    => 1,
        ]);
        if ($vendorItem->wasRecentlyCreated) {
            $this->logVendorItemCreated($vendorItem);
        }
        return PurchaseOrderContent::create([
            "order_id"          => $this->purchaseOrder->id,
            "item_vendor_id"    => $vendorItem->id,
            "quantity"          => $toReceive->expectedQuantity,
            "status"            => PurchaseOrderContent::STATUS_PENDING
        ]);
    }

    private function logMessage($translateKey, $replaceArray)
    {
        $message = __("mojito.{$translateKey}");
        collect($replaceArray)->each(function ($value, $key) use (&$message) {
            $message = str_replace("{{$key}}", "$value", $message);
        });
        array_push($this->changes, $message);
        return $message;
    }

    private function logContentsQuantityUpdated($purchaseOrderContent, $receivedQuantity)
    {
        $this->logMessage('purchaseContentsQuantityUpdated', [
            1 => nameOrDash($purchaseOrderContent->vendorItem->item),
            2 => $purchaseOrderContent->quantity,
            3 => $receivedQuantity,
        ]);
    }

    private function logContentsReceived($purchaseOrderContent)
    {
        $this->logMessage('purchaseContentsReceived', [
            1 => $purchaseOrderContent->received,
            2 => $purchaseOrderContent->quantity,
            3 => nameOrDash($purchaseOrderContent->vendorItem->item),
            4 => nameOrDash($this->warehouse)
        ]);
    }

    private function logVendorItemCreated($vendorItem)
    {
        $this->logMessage("vendorItemAddedToVendor", [
            1 => nameOrDash($vendorItem->item),
            2 => nameOrDash($vendorItem->vendor)
        ]);
    }
}

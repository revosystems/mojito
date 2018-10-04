<?php

namespace BadChoice\Mojito\Services\PurchaseOrders;

class PurchaseOrderLogger
{
    private $changes = [];

    public function getChanges()
    {
        return $this->changes;
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

    public function contentsQuantityUpdated($purchaseOrderContent, $receivedQuantity)
    {
        $this->logMessage('purchaseContentsQuantityUpdated', [
            1 => nameOrDash($purchaseOrderContent->vendorItem->item),
            2 => $purchaseOrderContent->quantity,
            3 => $receivedQuantity,
        ]);
    }

    public function contentsReceived($purchaseOrderContent, $warehouse)
    {
        $this->logMessage('purchaseContentsReceived', [
            1 => $purchaseOrderContent->received,
            2 => $purchaseOrderContent->quantity,
            3 => nameOrDash($purchaseOrderContent->vendorItem->item),
            4 => nameOrDash($warehouse)
        ]);
    }

    public function vendorItemCreated($vendorItem)
    {
        $this->logMessage("vendorItemAddedToVendor", [
            1 => nameOrDash($vendorItem->item),
            2 => nameOrDash($vendorItem->vendor)
        ]);
    }

    public function fullyReceived($purchaseOrder)
    {
        $this->logMessage('fullyReceived', [1 => $purchaseOrder->id, 2 => timeZoned($purchaseOrder->updated_at)]);
    }
}
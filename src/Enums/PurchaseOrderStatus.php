<?php

namespace BadChoice\Mojito\Enums;

enum PurchaseOrderStatus: int
{
    case STATUS_PENDING          = 0;
    case STATUS_SENT             = 1;
    case STATUS_PARTIAL_RECEIVED = 2;
    case STATUS_RECEIVED         = 3;
    case STATUS_DRAFT            = 4;

    public function color(): string
    {
        return match($this) {
            self::STATUS_PENDING          => 'blue-400',
            self::STATUS_SENT             => 'yellow-400',
            self::STATUS_PARTIAL_RECEIVED => 'red-400',
            self::STATUS_RECEIVED         => 'gray-600',
            self::STATUS_DRAFT            => 'gray-300',
        };
    }

    public function label(): string
    {
        return match($this) {
            self::STATUS_PENDING          => __('admin.pending'),
            self::STATUS_SENT             => __('admin.sent'),
            self::STATUS_PARTIAL_RECEIVED => __('admin.partialReceived'),
            self::STATUS_RECEIVED         => __('admin.received'),
            self::STATUS_DRAFT            => __('admin.draft'),
        };
    }
}

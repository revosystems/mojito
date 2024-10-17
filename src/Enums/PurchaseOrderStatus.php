<?php

namespace BadChoice\Mojito\Enums;

enum PurchaseOrderStatus: int
{
    case STATUS_PENDING           = 0;
    case STATUS_SENT              = 1;
    case STATUS_PARTIAL_RECEIVED  = 2;
    case STATUS_COMPLETED         = 3;
    case STATUS_DRAFT             = 4;
    case STATUS_PARTIAL_COMPLETED = 5;

    public function bgColor(): string
    {
        return match($this) {
            self::STATUS_PENDING           => 'bg-blue-400/20',
            self::STATUS_SENT              => 'bg-yellow-400/20',
            self::STATUS_PARTIAL_RECEIVED  => 'bg-yellow-400/20',
            self::STATUS_COMPLETED         => 'bg-green-400/20',
            self::STATUS_DRAFT             => 'bg-zinc-400/20',
            self::STATUS_PARTIAL_COMPLETED => 'bg-green-400/20',
        };
    }

    public function textColor(): string
    {
        return match($this) {
            self::STATUS_PENDING           => 'text-blue-800',
            self::STATUS_SENT              => 'text-yellow-800',
            self::STATUS_PARTIAL_RECEIVED  => 'text-yellow-800',
            self::STATUS_COMPLETED         => 'text-green-800',
            self::STATUS_DRAFT             => 'text-zinc-800',
            self::STATUS_PARTIAL_COMPLETED => 'text-green-800',
        };
    }

    public function label(): string
    {
        return match($this) {
            self::STATUS_PENDING           => __('admin.pending'),
            self::STATUS_SENT              => __('admin.sent'),
            self::STATUS_PARTIAL_RECEIVED  => __('admin.partialReceived'),
            self::STATUS_COMPLETED         => __('admin.completed'),
            self::STATUS_DRAFT             => __('admin.draft'),
            self::STATUS_PARTIAL_COMPLETED => __('admin.partialCompleted'),
        };
    }

    public static function statusArray()
    {
        return [
            PurchaseOrderStatus::STATUS_PENDING->value           => __('admin.pending'),
            PurchaseOrderStatus::STATUS_SENT->value              => __('admin.sent'),
            PurchaseOrderStatus::STATUS_PARTIAL_RECEIVED->value  => __('admin.partialReceived'),
            PurchaseOrderStatus::STATUS_COMPLETED->value         => __('admin.completed'),
            PurchaseOrderStatus::STATUS_DRAFT->value             => __('admin.draft'),
            PurchaseOrderStatus::STATUS_PARTIAL_COMPLETED->value => __('admin.partialCompleted'),
        ];
    }
}

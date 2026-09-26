<?php

namespace App\Services;

use App\Enums\MerchantOrderStatus;
use App\Enums\OrderStatus;
use App\Enums\StockReservationStatus;
use App\Models\Order;

class OrderStatusService
{
    public function synchronize(int $orderId): Order
    {
        $order = Order::query()->whereKey($orderId)->lockForUpdate()->firstOrFail();
        $statuses = $order->merchantOrders()
            ->get(['status'])
            ->pluck('status')
            ->map(fn (MerchantOrderStatus|string $status) => $status instanceof MerchantOrderStatus ? $status->value : $status);

        if ($statuses->contains(MerchantOrderStatus::PendingConfirmation->value)) {
            $status = $statuses->contains(MerchantOrderStatus::Confirmed->value)
                ? OrderStatus::PartiallyConfirmed
                : OrderStatus::PendingConfirmation;
        } elseif ($statuses->isNotEmpty() && $statuses->every(
            fn (string $status) => $status === MerchantOrderStatus::Confirmed->value
        )) {
            $status = OrderStatus::Confirmed;
        } elseif ($statuses->contains(MerchantOrderStatus::Confirmed->value)) {
            $status = OrderStatus::PartiallyConfirmed;
        } elseif ($statuses->isNotEmpty() && $statuses->every(
            fn (string $status) => $status === MerchantOrderStatus::Expired->value
        )) {
            $status = OrderStatus::Expired;
        } elseif ($statuses->isNotEmpty()) {
            $status = OrderStatus::Rejected;
        } else {
            $status = OrderStatus::Pending;
        }

        $nextExpiry = $order->reservations()
            ->where('status', StockReservationStatus::Reserved->value)
            ->min('expires_at');

        $order->update([
            'status' => $status,
            'reservation_expires_at' => $nextExpiry,
        ]);

        return $order->refresh();
    }
}

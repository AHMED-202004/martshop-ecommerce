<?php

namespace App\Services;

use App\Enums\MerchantOrderStatus;
use App\Enums\StockReservationStatus;
use App\Models\MerchantOrder;
use App\Models\OfferVariant;
use App\Models\ProductOffer;
use App\Models\StockReservation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockReservationService
{
    public function __construct(
        private readonly OrderStatusService $orderStatuses,
        private readonly AuditLogger $audit,
    ) {}

    public function consume(MerchantOrder $merchantOrder): int
    {
        $reservations = StockReservation::query()
            ->where('merchant_order_id', $merchantOrder->id)
            ->where('status', StockReservationStatus::Reserved->value)
            ->lockForUpdate()
            ->get();

        $this->assertComplete($merchantOrder, $reservations);

        foreach ($reservations as $reservation) {
            $reservation->update([
                'status' => StockReservationStatus::Consumed,
                'consumed_at' => now(),
            ]);
        }

        return $reservations->count();
    }

    public function release(
        MerchantOrder $merchantOrder,
        StockReservationStatus $releasedStatus,
        string $reason,
    ): int {
        $reservations = StockReservation::query()
            ->where('merchant_order_id', $merchantOrder->id)
            ->where('status', StockReservationStatus::Reserved->value)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $this->assertComplete($merchantOrder, $reservations);

        $offerQuantities = $reservations
            ->where('offer_stock_was_tracked', true)
            ->whereNotNull('product_offer_id')
            ->groupBy('product_offer_id')
            ->map(fn ($rows) => $rows->sum('quantity'));
        $variantQuantities = $reservations
            ->where('variant_stock_was_tracked', true)
            ->whereNotNull('offer_variant_id')
            ->groupBy('offer_variant_id')
            ->map(fn ($rows) => $rows->sum('quantity'));

        $offers = ProductOffer::query()
            ->whereIn('id', $offerQuantities->keys()->sort()->values())
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        $variants = OfferVariant::query()
            ->whereIn('id', $variantQuantities->keys()->sort()->values())
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($offerQuantities as $offerId => $quantity) {
            $offers->get($offerId)?->increment('stock', $quantity);
        }
        foreach ($variantQuantities as $variantId => $quantity) {
            $variants->get($variantId)?->increment('stock', $quantity);
        }
        foreach ($reservations as $reservation) {
            $reservation->update([
                'status' => $releasedStatus,
                'released_at' => now(),
                'release_reason' => $reason,
            ]);
        }

        return $reservations->count();
    }

    public function releaseExpired(int $batchSize = 100): int
    {
        $merchantOrderIds = StockReservation::query()
            ->where('status', StockReservationStatus::Reserved->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->distinct()
            ->limit(max(1, $batchSize))
            ->pluck('merchant_order_id');

        $released = 0;
        foreach ($merchantOrderIds as $merchantOrderId) {
            $released += DB::transaction(function () use ($merchantOrderId) {
                $merchantOrder = MerchantOrder::query()
                    ->whereKey($merchantOrderId)
                    ->lockForUpdate()
                    ->first();

                if (! $merchantOrder || $merchantOrder->status !== MerchantOrderStatus::PendingConfirmation) {
                    return 0;
                }

                $hasExpiredReservation = $merchantOrder->reservations()
                    ->where('status', StockReservationStatus::Reserved->value)
                    ->where('expires_at', '<=', now())
                    ->exists();
                if (! $hasExpiredReservation) {
                    return 0;
                }

                $before = ['status' => $merchantOrder->status->value];
                $count = $this->release(
                    $merchantOrder,
                    StockReservationStatus::Expired,
                    'confirmation_timeout',
                );
                $merchantOrder->update([
                    'status' => MerchantOrderStatus::Expired,
                    'expired_at' => now(),
                ]);
                $this->orderStatuses->synchronize($merchantOrder->order_id);
                $this->audit->record(
                    'merchant_order.expired',
                    $merchantOrder,
                    $before,
                    ['status' => MerchantOrderStatus::Expired->value, 'released_reservations' => $count],
                    'confirmation_timeout',
                );

                return $count;
            }, 3);
        }

        return $released;
    }

    private function assertComplete(MerchantOrder $merchantOrder, Collection $reservations): void
    {
        $items = $merchantOrder->items()->get([
            'id', 'order_id', 'merchant_order_id', 'product_offer_id', 'merchant_id', 'qty', 'variant_snapshot',
        ]);
        if ($items->isEmpty() || $items->count() !== $reservations->count()
            || $reservations->pluck('order_item_id')->unique()->count() !== $reservations->count()) {
            $this->invalidIntegrity();
        }

        $byItem = $reservations->keyBy('order_item_id');
        foreach ($items as $item) {
            $reservation = $byItem->get($item->id);
            $variantId = data_get($item->variant_snapshot, 'offer_variant_id');
            if (! $reservation
                || $item->order_id !== $merchantOrder->order_id
                || $item->merchant_order_id !== $merchantOrder->id
                || $item->merchant_id !== $merchantOrder->merchant_id
                || $reservation->order_id !== $merchantOrder->order_id
                || $reservation->merchant_order_id !== $merchantOrder->id
                || $reservation->product_offer_id !== $item->product_offer_id
                || $reservation->offer_variant_id !== ($variantId === null ? null : (int) $variantId)
                || $reservation->quantity !== (int) $item->qty
                || $reservation->quantity < 1) {
                $this->invalidIntegrity();
            }
        }
    }

    private function invalidIntegrity(): never
    {
        throw ValidationException::withMessages([
            'order' => 'بيانات حجز المخزون لا تطابق عناصر الطلب؛ يلزم تدخل إداري قبل تغيير الحالة.',
        ]);
    }
}

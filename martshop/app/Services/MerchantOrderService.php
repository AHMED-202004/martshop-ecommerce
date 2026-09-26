<?php

namespace App\Services;

use App\Enums\MerchantVerificationStatus;
use App\Enums\MerchantOrderStatus;
use App\Enums\StockReservationStatus;
use App\Models\Merchant;
use App\Models\MerchantOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MerchantOrderService
{
    public function __construct(
        private readonly StockReservationService $reservations,
        private readonly OrderStatusService $orderStatuses,
        private readonly AuditLogger $audit,
    ) {}

    public function confirm(MerchantOrder $merchantOrder, User $actor): MerchantOrder
    {
        return DB::transaction(function () use ($merchantOrder, $actor) {
            $locked = MerchantOrder::query()->whereKey($merchantOrder->id)->lockForUpdate()->firstOrFail();
            $this->authorizeOwner($locked, $actor);

            if ($locked->status === MerchantOrderStatus::Confirmed) {
                return $locked;
            }
            if ($locked->status !== MerchantOrderStatus::PendingConfirmation) {
                throw ValidationException::withMessages(['order' => 'لم يعد هذا الطلب بانتظار التأكيد.']);
            }

            $before = ['status' => $locked->status->value];
            $hasExpiredReservation = $locked->reservations()
                ->where('status', StockReservationStatus::Reserved->value)
                ->where('expires_at', '<=', now())
                ->exists();
            if ($hasExpiredReservation) {
                $count = $this->reservations->release(
                    $locked,
                    StockReservationStatus::Expired,
                    'confirmation_timeout',
                );
                $locked->update([
                    'status' => MerchantOrderStatus::Expired,
                    'expired_at' => now(),
                ]);
                $this->orderStatuses->synchronize($locked->order_id);
                $this->audit->record(
                    'merchant_order.expired',
                    $locked,
                    $before,
                    ['status' => MerchantOrderStatus::Expired->value, 'released_reservations' => $count],
                    'confirmation_timeout',
                    ['actor_id' => $actor->id, 'trigger' => 'late_confirmation'],
                );

                return $locked->refresh();
            }

            $count = $this->reservations->consume($locked);
            $locked->update([
                'status' => MerchantOrderStatus::Confirmed,
                'confirmed_at' => now(),
            ]);
            $this->orderStatuses->synchronize($locked->order_id);
            $this->audit->record(
                'merchant_order.confirmed',
                $locked,
                $before,
                ['status' => MerchantOrderStatus::Confirmed->value, 'consumed_reservations' => $count],
                metadata: ['actor_id' => $actor->id],
            );

            return $locked->refresh();
        }, 3);
    }

    public function reject(MerchantOrder $merchantOrder, User $actor, string $reason): MerchantOrder
    {
        return DB::transaction(function () use ($merchantOrder, $actor, $reason) {
            $locked = MerchantOrder::query()->whereKey($merchantOrder->id)->lockForUpdate()->firstOrFail();
            $this->authorizeOwner($locked, $actor);

            if ($locked->status === MerchantOrderStatus::Rejected) {
                return $locked;
            }
            if ($locked->status !== MerchantOrderStatus::PendingConfirmation) {
                throw ValidationException::withMessages(['order' => 'لم يعد هذا الطلب بانتظار التأكيد.']);
            }

            $before = ['status' => $locked->status->value];
            $count = $this->reservations->release(
                $locked,
                StockReservationStatus::Released,
                'merchant_rejected',
            );
            $locked->update([
                'status' => MerchantOrderStatus::Rejected,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);
            $this->orderStatuses->synchronize($locked->order_id);
            $this->audit->record(
                'merchant_order.rejected',
                $locked,
                $before,
                ['status' => MerchantOrderStatus::Rejected->value, 'released_reservations' => $count],
                $reason,
                ['actor_id' => $actor->id],
            );

            return $locked->refresh();
        }, 3);
    }

    private function authorizeOwner(MerchantOrder $merchantOrder, User $actor): void
    {
        $isVerifiedOwner = Merchant::query()
            ->whereKey($merchantOrder->merchant_id)
            ->where('user_id', $actor->id)
            ->where('verification_status', MerchantVerificationStatus::Verified->value)
            ->exists();

        abort_unless($isVerifiedOwner, 403);
    }
}

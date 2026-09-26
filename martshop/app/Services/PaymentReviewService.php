<?php

namespace App\Services;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentReviewService
{
    public function __construct(
        private readonly MarketplaceSettings $money,
        private readonly MerchantLedgerService $ledger,
        private readonly AuditLogger $audit,
    ) {}

    public function review(
        Payment $payment,
        PaymentStatus $decision,
        User $actor,
        ?string $reason,
        int $lockVersion,
    ): Payment {
        abort_unless($actor->hasPermission('payments.verify'), 403);

        return DB::transaction(function () use ($payment, $decision, $actor, $reason, $lockVersion) {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($locked->user_id === $actor->id) {
                throw ValidationException::withMessages([
                    'payment' => 'لا يمكن لمراجع الدفعات اتخاذ قرار على دفعة تخص حسابه.',
                ]);
            }
            if ($locked->status !== PaymentStatus::Pending) {
                if ($locked->status === $decision) {
                    if ($decision === PaymentStatus::Accepted) {
                        $acceptedOrder = Order::query()
                            ->whereKey($locked->order_id)
                            ->lockForUpdate()
                            ->firstOrFail();
                        $this->ledger->recordAcceptedPayment($acceptedOrder, $locked, $actor);
                    }
                    return $locked;
                }

                throw ValidationException::withMessages(['payment' => 'تمت مراجعة هذه الدفعة مسبقًا.']);
            }
            if ($locked->lock_version !== $lockVersion) {
                throw ValidationException::withMessages(['lock_version' => 'تم تحديث الدفعة من موظف آخر. حدّث الصفحة.']);
            }

            $order = Order::query()->whereKey($locked->order_id)->lockForUpdate()->firstOrFail();
            $expectedAmount = $this->money->decimalToMinorUnits($order->total);
            $this->validateDecision($decision, $locked->amount, $expectedAmount, $order);
            $before = [
                'status' => $locked->status->value,
                'lock_version' => $locked->lock_version,
            ];

            $accepted = $decision === PaymentStatus::Accepted;
            $locked->update([
                'status' => $decision,
                'open_key' => $accepted ? 'order:'.$order->id : null,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_notes' => $reason,
                'accepted_at' => $accepted ? now() : null,
                'rejected_at' => $accepted ? null : now(),
                'lock_version' => $locked->lock_version + 1,
            ]);
            $order->update([
                'payment_status' => $accepted
                    ? OrderPaymentStatus::Paid
                    : OrderPaymentStatus::ActionRequired,
            ]);
            if ($accepted) {
                $this->ledger->recordAcceptedPayment($order, $locked, $actor);
            }

            $this->audit->record(
                'payment.reviewed',
                $locked,
                $before,
                [
                    'status' => $decision->value,
                    'lock_version' => $locked->lock_version,
                    'order_payment_status' => $order->payment_status->value,
                ],
                $reason,
                ['actor_id' => $actor->id, 'order_id' => $order->id],
            );

            return $locked->refresh();
        }, 3);
    }

    private function validateDecision(
        PaymentStatus $decision,
        int $amount,
        int $expectedAmount,
        Order $order,
    ): void {
        if ($decision === PaymentStatus::Accepted) {
            if ($order->status !== OrderStatus::Confirmed) {
                throw ValidationException::withMessages(['payment' => 'لا يمكن قبول دفعة لطلب غير مؤكد.']);
            }
            if ($amount !== $expectedAmount) {
                throw ValidationException::withMessages([
                    'decision' => $amount < $expectedAmount
                        ? 'المبلغ ناقص؛ اختر حالة مبلغ ناقص.'
                        : 'المبلغ زائد؛ اختر حالة مبلغ زائد.',
                ]);
            }
        }
        if ($decision === PaymentStatus::ShortAmount && $amount >= $expectedAmount) {
            throw ValidationException::withMessages(['decision' => 'المبلغ المسجل ليس أقل من قيمة الطلب.']);
        }
        if ($decision === PaymentStatus::Overpaid && $amount <= $expectedAmount) {
            throw ValidationException::withMessages(['decision' => 'المبلغ المسجل ليس أكبر من قيمة الطلب.']);
        }
    }
}

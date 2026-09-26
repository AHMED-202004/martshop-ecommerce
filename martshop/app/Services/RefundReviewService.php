<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\LedgerEntryType;
use App\Enums\LedgerStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Delivery;
use App\Models\LedgerEntry;
use App\Models\RefundRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RefundReviewService
{
    public function __construct(private readonly MarketplaceSettings $money, private readonly AuditLogger $audit) {}

    public function request(Delivery $delivery, User $actor, string $reason): RefundRequest
    {
        return DB::transaction(function () use ($delivery, $actor, $reason) {
            // Same lock as dispute closure and settlement: these operations cannot interleave.
            $locked = Delivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->order->user_id === $actor->id, 403);
            $dispute = $locked->dispute;
            if ($existing = $dispute?->refundRequest) {
                return $existing;
            }
            [$sale, $snapshot] = $this->eligibleSnapshot($locked);
            $refund = RefundRequest::query()->create([
                'delivery_dispute_id' => $dispute->id, 'sale_entry_id' => $sale->id,
                'requested_by' => $actor->id, 'reference' => 'RFD-'.Str::upper((string) Str::ulid()),
                'status' => RefundStatus::Requested, 'amount' => $snapshot['total_minor'],
                'currency' => $sale->currency, 'amount_snapshot' => $snapshot, 'reason' => $reason,
            ]);
            $this->audit->record('refund.requested', $refund, null, [
                'status' => $refund->status->value, 'amount' => $refund->amount,
                'currency' => $refund->currency, 'delivery_id' => $locked->id,
            ], $reason);

            return $refund;
        }, 3);
    }

    public function review(RefundRequest $refund, User $actor, RefundStatus $decision, string $reason, int $version): RefundRequest
    {
        abort_unless($actor->hasPermission('refunds.review'), 403);
        if (! in_array($decision, [RefundStatus::Approved, RefundStatus::Rejected], true)) {
            $this->invalid('قرار المراجعة غير صالح.');
        }

        return DB::transaction(function () use ($refund, $actor, $decision, $reason, $version) {
            $delivery = Delivery::query()->whereKey($refund->dispute->delivery_id)->lockForUpdate()->firstOrFail();
            $locked = RefundRequest::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            abort_if($delivery->order->user_id === $actor->id, 403);
            if ($locked->status === $decision) {
                return $locked;
            }
            if ($locked->status !== RefundStatus::Requested || $locked->lock_version !== $version) {
                $this->invalid('تمت مراجعة الطلب أو تغييره؛ حدّث الصفحة.');
            }
            if ($decision === RefundStatus::Approved) {
                $this->assertFinancialSnapshot($locked, $delivery);
            }
            $locked->update([
                'status' => $decision, 'reviewed_by' => $actor->id, 'review_reason' => $reason,
                'reviewed_at' => now(), 'lock_version' => $locked->lock_version + 1,
            ]);
            // Review does not move money, close the dispute, or mark a refund paid.
            $this->audit->record('refund.reviewed', $locked, ['status' => RefundStatus::Requested->value], [
                'status' => $decision->value, 'amount' => $locked->amount, 'currency' => $locked->currency,
            ], $reason);

            return $locked;
        }, 3);
    }

    // Caller holds the delivery/refund locks when used for financial mutation.
    public function assertFinancialSnapshot(RefundRequest $refund, Delivery $delivery): LedgerEntry
    {
        [$sale, $snapshot] = $this->eligibleSnapshot($delivery);
        if ($sale->id !== $refund->sale_entry_id || $snapshot !== $refund->amount_snapshot
            || $refund->amount !== $snapshot['total_minor'] || $refund->currency !== $sale->currency
            || $refund->delivery_dispute_id !== $delivery->dispute->id) {
            $this->invalid('تغيرت بيانات الطلب المالية؛ تلزم مراجعة منفصلة.');
        }

        return $sale;
    }

    private function eligibleSnapshot(Delivery $delivery): array
    {
        if ($delivery->settled_at || $delivery->status !== DeliveryStatus::Delivered
            || $delivery->dispute?->status !== 'open' || $delivery->order->payment_status !== OrderPaymentStatus::Paid) {
            $this->invalid('يلزم نزاع مفتوح على طلب مسلّم ومدفوع ولم تُحرّر مستحقاته.');
        }
        $suborder = $delivery->merchantOrder;
        $sales = LedgerEntry::query()->where('merchant_order_id', $suborder->id)
            ->where('order_id', $delivery->order_id)->where('merchant_id', $suborder->merchant_id)
            ->where('entry_type', LedgerEntryType::Sale->value)->where('status', LedgerStatus::Pending->value)->get();
        if ($sales->count() !== 1) {
            $this->invalid('قيد البيع غير مكتمل؛ تلزم مراجعة مالية.');
        }
        $sale = $sales->first();
        $payment = $sale->payment;
        $snapshot = [
            'scope' => 'full_suborder_including_delivery_and_service',
            'product_minor' => $this->money->decimalToMinorUnits($suborder->product_subtotal),
            'delivery_minor' => $this->money->decimalToMinorUnits($suborder->delivery_fee),
            'service_minor' => $this->money->decimalToMinorUnits($suborder->service_fee),
            'commission_minor' => $this->money->decimalToMinorUnits($suborder->commission_amount),
            'merchant_net_minor' => $sale->net_amount,
            'total_minor' => $this->money->decimalToMinorUnits($suborder->total),
        ];
        $amount = $snapshot['total_minor'];
        if (! $payment || $payment->status !== PaymentStatus::Accepted
            || $payment->order_id !== $delivery->order_id || $payment->user_id !== $delivery->order->user_id
            || $payment->currency !== $suborder->currency || $sale->currency !== $suborder->currency
            || $payment->amount !== $this->money->decimalToMinorUnits($delivery->order->total)
            || $amount <= 0 || $amount > $payment->amount
            || min(array_slice($snapshot, 1)) < 0
            || $amount !== $snapshot['product_minor'] + $snapshot['delivery_minor'] + $snapshot['service_minor']
            || $sale->gross_amount !== $snapshot['product_minor']
            || $sale->commission_amount !== $snapshot['commission_minor']
            || $sale->fee_amount !== 0
            || $sale->net_amount !== $snapshot['product_minor'] - $snapshot['commission_minor']) {
            $this->invalid('الدفع أو مبالغ الطلب غير متطابقة؛ تلزم مراجعة مالية.');
        }
        $entries = LedgerEntry::query()->where('merchant_order_id', $suborder->id)
            ->where('merchant_id', $sale->merchant_id)->where('currency', $sale->currency)->get();
        foreach (LedgerStatus::cases() as $bucket) {
            $expected = $bucket === LedgerStatus::Held ? $sale->net_amount : 0;
            if ((int) $entries->where('status', $bucket)->sum('net_amount') !== $expected) {
                $this->invalid('رصيد الطلب المحجوز غير متطابق؛ تلزم مراجعة مالية.');
            }
        }

        return [$sale, $snapshot];
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['refund' => $message]);
    }
}

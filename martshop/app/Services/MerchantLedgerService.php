<?php

namespace App\Services;

use App\Enums\LedgerEntryType;
use App\Enums\LedgerStatus;
use App\Enums\MerchantOrderStatus;
use App\Enums\DeliveryStatus;
use App\Enums\MerchantVerificationStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\WithdrawalStatus;
use App\Models\LedgerEntry;
use App\Models\Delivery;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MerchantLedgerService
{
    public function __construct(
        private readonly MarketplaceSettings $money,
        private readonly AuditLogger $audit,
    ) {}

    public function recordAcceptedPayment(Order $order, Payment $payment, User $actor): Collection
    {
        abort_unless($actor->hasPermission('payments.verify'), 403);

        $merchantOrders = $order->merchantOrders()
            ->whereNotNull('merchant_id')
            ->where('status', MerchantOrderStatus::Confirmed->value)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        return $merchantOrders->map(function ($merchantOrder) use ($order, $payment, $actor) {
            $gross = $this->money->decimalToMinorUnits($merchantOrder->product_subtotal);
            $commission = $this->money->decimalToMinorUnits($merchantOrder->commission_amount);
            $fees = 0;
            $net = $gross - $commission - $fees;
            if ($gross < 0 || $commission < 0 || $commission > $gross || $net < 0) {
                throw ValidationException::withMessages([
                    'payment' => "بيانات تسوية طلب التاجر #{$merchantOrder->id} غير صالحة.",
                ]);
            }

            $idempotencyKey = 'payment:'.$payment->id.':merchant-order:'.$merchantOrder->id.':sale';
            $entry = LedgerEntry::query()->firstOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'merchant_id' => $merchantOrder->merchant_id,
                    'order_id' => $order->id,
                    'merchant_order_id' => $merchantOrder->id,
                    'payment_id' => $payment->id,
                    'entry_type' => LedgerEntryType::Sale,
                    'direction' => 'credit',
                    'amount' => $net,
                    'gross_amount' => $gross,
                    'commission_amount' => $commission,
                    'fee_amount' => $fees,
                    'net_amount' => $net,
                    'status' => LedgerStatus::Pending,
                    'currency' => $merchantOrder->currency,
                    'reference' => 'sale:merchant-order:'.$merchantOrder->id,
                    'metadata' => [
                        'commission_snapshot' => $merchantOrder->commission_snapshot,
                        'delivery_snapshot' => $merchantOrder->delivery_snapshot,
                        'payment_status' => $payment->status->value,
                    ],
                    'created_by' => $actor->id,
                    'created_at' => now(),
                ],
            );

            if ($entry->wasRecentlyCreated) {
                $this->audit->record('ledger.sale_recorded', $entry, null, [
                    'merchant_id' => $entry->merchant_id,
                    'merchant_order_id' => $entry->merchant_order_id,
                    'gross_amount' => $gross,
                    'commission_amount' => $commission,
                    'net_amount' => $net,
                    'status' => LedgerStatus::Pending->value,
                ]);
            }

            return $entry;
        });
    }

    public function balancesForMerchant(int $merchantId): array
    {
        $balances = LedgerEntry::query()
            ->where('merchant_id', $merchantId)
            ->selectRaw('status, currency, SUM(net_amount) as balance')
            ->groupBy('status', 'currency')
            ->get();

        return collect(LedgerStatus::cases())->mapWithKeys(function (LedgerStatus $status) use ($balances) {
            return [$status->value => $balances
                ->where('status', $status)
                ->mapWithKeys(fn ($row) => [$row->currency => (int) $row->balance])
                ->all()];
        })->all();
    }

    public function bucketBalance(
        int $merchantId,
        LedgerStatus $status,
        string $currency = 'ILS',
        bool $lock = false,
    ): int {
        $query = LedgerEntry::query()
            ->where('merchant_id', $merchantId)
            ->where('status', $status->value)
            ->where('currency', $currency)
            ->orderBy('id');

        if ($lock) {
            return (int) $query->lockForUpdate()->get(['net_amount'])->sum('net_amount');
        }

        return (int) $query->sum('net_amount');
    }

    public function releaseDeliveredSale(Delivery $delivery, ?User $actor = null): Collection
    {
        return DB::transaction(function () use ($delivery, $actor) {
            $locked = Delivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            if ($locked->settled_at) {
                $entries = LedgerEntry::query()->whereIn('idempotency_key', [
                    "delivery:{$locked->id}:settlement:pending:debit",
                    "delivery:{$locked->id}:settlement:available:credit",
                ])->get();
                if ($entries->count() !== 2) {
                    throw ValidationException::withMessages(['settlement' => 'قيود التسوية غير مكتملة.']);
                }
                return $entries;
            }
            if (! $this->money->boolean('settlement.auto_release_enabled')
                || $locked->status !== DeliveryStatus::Delivered
                || ! $locked->settlement_due_at || $locked->settlement_due_at->isFuture()
                || ! $locked->proof()->exists()
                || $locked->dispute()->whereIn('status', ['open', 'refunded'])->exists()
                || $locked->order->payment_status !== OrderPaymentStatus::Paid) {
                throw ValidationException::withMessages(['settlement' => 'التسوية غير مؤهلة للتحرير حاليًا.']);
            }
            $entries = $this->recordDeliveredRelease($locked, $actor);
            $locked->update(['settled_at' => now()]);

            return $entries;
        }, 3);
    }

    private function recordDeliveredRelease(Delivery $delivery, ?User $actor): Collection
    {
        $sale = $this->saleForDelivery($delivery);

        $debitKey = "delivery:{$delivery->id}:settlement:pending:debit";
        $creditKey = "delivery:{$delivery->id}:settlement:available:credit";
        $existing = LedgerEntry::query()
            ->whereIn('idempotency_key', [$debitKey, $creditKey])
            ->orderBy('id')->lockForUpdate()->get();
        if ($existing->count() === 2) {
            return $existing;
        }
        if ($existing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'delivery' => 'حركة تسوية التسليم غير مكتملة وتحتاج مراجعة إدارية.',
            ]);
        }

        $this->ensureDeliveryBalance($sale, LedgerStatus::Pending);

        $common = [
            'merchant_id' => $sale->merchant_id,
            'order_id' => $sale->order_id,
            'merchant_order_id' => $sale->merchant_order_id,
            'payment_id' => $sale->payment_id,
            'entry_type' => LedgerEntryType::SettlementRelease,
            'gross_amount' => 0,
            'commission_amount' => 0,
            'fee_amount' => 0,
            'currency' => $sale->currency,
            'metadata' => [
                'delivery_id' => $delivery->id,
                'delivery_reference' => $delivery->reference,
                'sale_entry_id' => $sale->id,
                'from' => LedgerStatus::Pending->value,
                'to' => LedgerStatus::Available->value,
            ],
            'created_by' => $actor?->id,
            'created_at' => now(),
        ];
        $debit = LedgerEntry::query()->create($common + [
            'direction' => 'debit',
            'amount' => -$sale->net_amount,
            'net_amount' => -$sale->net_amount,
            'status' => LedgerStatus::Pending,
            'reference' => $debitKey,
            'idempotency_key' => $debitKey,
        ]);
        $credit = LedgerEntry::query()->create($common + [
            'direction' => 'credit',
            'amount' => $sale->net_amount,
            'net_amount' => $sale->net_amount,
            'status' => LedgerStatus::Available,
            'reference' => $creditKey,
            'idempotency_key' => $creditKey,
        ]);
        $this->audit->record('ledger.delivery_settled', $delivery, null, [
            'merchant_id' => $sale->merchant_id,
            'amount' => $sale->net_amount,
            'currency' => $sale->currency,
            'debit_entry_id' => $debit->id,
            'credit_entry_id' => $credit->id,
        ]);

        return collect([$debit, $credit]);
    }

    public function holdDisputedSale(Delivery $delivery, User $actor): Collection
    {
        $this->authorizeDisputeMove($delivery, $actor, false);

        return $this->moveDisputedSale($delivery, $actor, LedgerStatus::Pending, LedgerStatus::Held, 'hold');
    }

    // Called within RefundTransferService's transaction after locking the delivery/refund.
    public function refundHeldSale(Delivery $delivery, \App\Models\RefundRequest $refund, User $actor): Collection
    {
        abort_unless($actor->hasPermission('refunds.pay'), 403);
        $sale = $this->saleForDelivery($delivery);
        if ($refund->status !== \App\Enums\RefundStatus::Processing || $refund->sale_entry_id !== $sale->id) {
            throw ValidationException::withMessages(['refund' => 'حالة الاسترداد لا تسمح بتسجيل الحركة المالية.']);
        }
        $this->ensureDeliveryBalance($sale, LedgerStatus::Held);
        $keys = ["refund:{$refund->id}:held:debit", "refund:{$refund->id}:refunded:credit"];
        if (LedgerEntry::query()->whereIn('idempotency_key', $keys)->exists()) {
            throw ValidationException::withMessages(['refund' => 'توجد حركة استرداد سابقة غير متطابقة مع حالة التحويل؛ تلزم مطابقة مالية.']);
        }
        $common = ['merchant_id' => $sale->merchant_id, 'order_id' => $sale->order_id,
            'merchant_order_id' => $sale->merchant_order_id, 'payment_id' => $sale->payment_id,
            'entry_type' => LedgerEntryType::Refund, 'currency' => $sale->currency,
            'created_by' => $actor->id, 'created_at' => now(),
            'metadata' => ['refund_request_id' => $refund->id, 'sale_entry_id' => $sale->id, 'delivery_id' => $delivery->id]];
        $debit = LedgerEntry::query()->create($common + ['direction' => 'debit', 'amount' => -$sale->net_amount,
            'net_amount' => -$sale->net_amount, 'status' => LedgerStatus::Held, 'reference' => $keys[0], 'idempotency_key' => $keys[0]]);
        $credit = LedgerEntry::query()->create($common + ['direction' => 'credit', 'amount' => $sale->net_amount,
            'net_amount' => $sale->net_amount, 'status' => LedgerStatus::Refunded, 'reference' => $keys[1], 'idempotency_key' => $keys[1]]);
        $this->audit->record('ledger.refund_recorded', $refund, null, ['merchant_net_amount' => $sale->net_amount,
            'debit_entry_id' => $debit->id, 'credit_entry_id' => $credit->id, 'currency' => $sale->currency]);

        return collect([$debit, $credit]);
    }

    public function restoreDisputedSale(Delivery $delivery, User $actor): Collection
    {
        $this->authorizeDisputeMove($delivery, $actor, true);

        return $this->moveDisputedSale($delivery, $actor, LedgerStatus::Held, LedgerStatus::Pending, 'restore');
    }

    // Caller holds the delivery row lock inside the dispute transaction.
    private function moveDisputedSale(Delivery $delivery, User $actor, LedgerStatus $from, LedgerStatus $to, string $phase): Collection
    {
        $sale = $this->saleForDelivery($delivery);
        $keys = ["delivery:{$delivery->id}:dispute:{$phase}:debit", "delivery:{$delivery->id}:dispute:{$phase}:credit"];
        $existing = LedgerEntry::query()->whereIn('idempotency_key', $keys)->lockForUpdate()->get();
        if ($existing->count() === 2) {
            return $existing;
        }
        if ($existing->isNotEmpty()) {
            throw ValidationException::withMessages(['dispute' => 'قيود حجز النزاع غير مكتملة.']);
        }
        $this->ensureDeliveryBalance($sale, $from);
        $common = [
            'merchant_id' => $sale->merchant_id, 'order_id' => $sale->order_id,
            'merchant_order_id' => $sale->merchant_order_id, 'payment_id' => $sale->payment_id,
            'entry_type' => $phase === 'hold' ? LedgerEntryType::DisputeHold : LedgerEntryType::DisputeRelease,
            'currency' => $sale->currency, 'created_by' => $actor->id, 'created_at' => now(),
            'metadata' => ['delivery_id' => $delivery->id, 'sale_entry_id' => $sale->id],
        ];
        $debit = LedgerEntry::query()->create($common + [
            'direction' => 'debit', 'amount' => -$sale->net_amount, 'net_amount' => -$sale->net_amount,
            'status' => $from, 'reference' => $keys[0], 'idempotency_key' => $keys[0],
        ]);
        $credit = LedgerEntry::query()->create($common + [
            'direction' => 'credit', 'amount' => $sale->net_amount, 'net_amount' => $sale->net_amount,
            'status' => $to, 'reference' => $keys[1], 'idempotency_key' => $keys[1],
        ]);
        $this->audit->record('ledger.dispute_'.$phase, $delivery, null, [
            'from' => $from->value, 'to' => $to->value, 'amount' => $sale->net_amount,
            'debit_entry_id' => $debit->id, 'credit_entry_id' => $credit->id,
        ]);

        return collect([$debit, $credit]);
    }

    private function saleForDelivery(Delivery $delivery): LedgerEntry
    {
        $sales = LedgerEntry::query()->where('merchant_order_id', $delivery->merchant_order_id)
            ->where('order_id', $delivery->order_id)
            ->where('merchant_id', $delivery->merchantOrder->merchant_id)
            ->where('entry_type', LedgerEntryType::Sale->value)
            ->where('status', LedgerStatus::Pending->value)->lockForUpdate()->get();
        if ($sales->count() !== 1 || $sales->first()->net_amount < 0) {
            throw ValidationException::withMessages(['settlement' => 'قيد البيع غير صالح؛ تلزم مراجعة مالية.']);
        }

        return $sales->first();
    }

    private function ensureDeliveryBalance(LedgerEntry $sale, LedgerStatus $bucket): void
    {
        $balance = LedgerEntry::query()->where('merchant_order_id', $sale->merchant_order_id)
            ->where('merchant_id', $sale->merchant_id)->where('currency', $sale->currency)
            ->where('status', $bucket->value)->lockForUpdate()->get(['net_amount'])->sum('net_amount');
        if ((int) $balance !== $sale->net_amount) {
            throw ValidationException::withMessages(['settlement' => 'رصيد الطلب لا يطابق قيد البيع؛ تلزم مراجعة مالية.']);
        }
    }

    public function holdWithdrawal(WithdrawalRequest $withdrawal, User $actor): Collection
    {
        $withdrawal = WithdrawalRequest::query()->whereKey($withdrawal->id)->firstOrFail();
        $isVerifiedOwner = Merchant::query()->whereKey($withdrawal->merchant_id)
            ->where('user_id', $actor->id)
            ->where('verification_status', MerchantVerificationStatus::Verified->value)
            ->exists();
        abort_unless($isVerifiedOwner && $withdrawal->status === WithdrawalStatus::Requested, 403);

        return $this->moveWithdrawalBucket(
            $withdrawal,
            LedgerStatus::Available,
            LedgerStatus::Held,
            LedgerEntryType::WithdrawalHold,
            'hold',
            'ledger.withdrawal_held',
            $actor,
        );
    }

    public function releaseWithdrawal(WithdrawalRequest $withdrawal, User $actor): Collection
    {
        $withdrawal = $this->authorizedWithdrawalStaffAction($withdrawal, $actor, WithdrawalStatus::Requested);

        return $this->moveWithdrawalBucket(
            $withdrawal,
            LedgerStatus::Held,
            LedgerStatus::Available,
            LedgerEntryType::WithdrawalRelease,
            'release',
            'ledger.withdrawal_released',
            $actor,
        );
    }

    public function payWithdrawal(WithdrawalRequest $withdrawal, User $actor): Collection
    {
        $withdrawal = $this->authorizedWithdrawalStaffAction($withdrawal, $actor, WithdrawalStatus::Approved);

        return $this->moveWithdrawalBucket(
            $withdrawal,
            LedgerStatus::Held,
            LedgerStatus::Withdrawn,
            LedgerEntryType::Withdrawal,
            'paid',
            'ledger.withdrawal_paid',
            $actor,
        );
    }

    private function moveWithdrawalBucket(
        WithdrawalRequest $withdrawal,
        LedgerStatus $from,
        LedgerStatus $to,
        LedgerEntryType $type,
        string $phase,
        string $auditAction,
        User $actor,
    ): Collection {
        $debitKey = "withdrawal:{$withdrawal->id}:{$phase}:{$from->value}:debit";
        $creditKey = "withdrawal:{$withdrawal->id}:{$phase}:{$to->value}:credit";
        $existing = LedgerEntry::query()
            ->whereIn('idempotency_key', [$debitKey, $creditKey])
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        if ($existing->count() === 2) {
            return $existing;
        }
        if ($existing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'withdrawal' => 'حركة السحب غير مكتملة محاسبيًا وتحتاج مراجعة إدارية.',
            ]);
        }

        $sourceBalance = $this->bucketBalance(
            $withdrawal->merchant_id,
            $from,
            $withdrawal->currency,
            true,
        );
        if ($withdrawal->amount <= 0 || $sourceBalance < $withdrawal->amount) {
            throw ValidationException::withMessages([
                'amount' => 'الرصيد في الحالة المطلوبة لا يكفي لإتمام حركة السحب.',
            ]);
        }

        $common = [
            'merchant_id' => $withdrawal->merchant_id,
            'withdrawal_request_id' => $withdrawal->id,
            'entry_type' => $type,
            'gross_amount' => 0,
            'commission_amount' => 0,
            'fee_amount' => 0,
            'currency' => $withdrawal->currency,
            'metadata' => [
                'withdrawal_reference' => $withdrawal->reference,
                'phase' => $phase,
                'from' => $from->value,
                'to' => $to->value,
            ],
            'created_by' => $actor->id,
            'created_at' => now(),
        ];
        $debit = LedgerEntry::query()->create($common + [
            'direction' => 'debit',
            'amount' => -$withdrawal->amount,
            'net_amount' => -$withdrawal->amount,
            'status' => $from,
            'reference' => $debitKey,
            'idempotency_key' => $debitKey,
        ]);
        $credit = LedgerEntry::query()->create($common + [
            'direction' => 'credit',
            'amount' => $withdrawal->amount,
            'net_amount' => $withdrawal->amount,
            'status' => $to,
            'reference' => $creditKey,
            'idempotency_key' => $creditKey,
        ]);

        $this->audit->record($auditAction, $withdrawal, null, [
            'merchant_id' => $withdrawal->merchant_id,
            'amount' => $withdrawal->amount,
            'currency' => $withdrawal->currency,
            'from' => $from->value,
            'to' => $to->value,
            'debit_entry_id' => $debit->id,
            'credit_entry_id' => $credit->id,
        ]);

        return collect([$debit, $credit]);
    }

    private function authorizeDisputeMove(Delivery $delivery, User $actor, bool $restoring): void
    {
        $delivery = Delivery::query()->with(['order:id,user_id', 'dispute.refundRequest'])->findOrFail($delivery->id);
        $isOwner = $delivery->order->user_id === $actor->id;
        $isIndependentStaff = $actor->hasPermission('settlements.manage') && ! $isOwner;
        abort_unless($restoring ? $isIndependentStaff : ($isOwner || $isIndependentStaff), 403);
        abort_unless($delivery->dispute?->status === 'open', 403);

        if ($restoring) {
            $refund = $delivery->dispute->refundRequest;
            abort_unless(! $refund || $refund->status === RefundStatus::Rejected, 403);
        }
    }

    private function authorizedWithdrawalStaffAction(
        WithdrawalRequest $withdrawal,
        User $actor,
        WithdrawalStatus $requiredStatus,
    ): WithdrawalRequest {
        abort_unless($actor->hasPermission('withdrawals.approve'), 403);
        $withdrawal = WithdrawalRequest::query()->whereKey($withdrawal->id)->firstOrFail();
        $ownsMerchant = Merchant::query()->whereKey($withdrawal->merchant_id)
            ->where('user_id', $actor->id)
            ->exists();
        abort_unless(! $ownsMerchant && $withdrawal->status === $requiredStatus, 403);

        return $withdrawal;
    }
}

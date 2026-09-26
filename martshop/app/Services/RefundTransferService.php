<?php

namespace App\Services;

use App\Enums\{RefundDestinationStatus, RefundStatus};
use App\Models\{Delivery, Order, RefundDestination, RefundRequest, RefundTransfer, User};
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, Storage, Validator};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class RefundTransferService
{
    public function __construct(private readonly RefundReviewService $review, private readonly MerchantLedgerService $ledger,
        private readonly MarketplaceSettings $money, private readonly AuditLogger $audit) {}

    public function prepare(RefundRequest $refund, User $actor, int $version, int $destinationId, int $destinationVersion): RefundTransfer
    {
        abort_unless($actor->hasPermission('refunds.pay'), 403);

        return DB::transaction(function () use ($refund, $actor, $version, $destinationId, $destinationVersion) {
            [$delivery, $locked] = $this->lockRefund($refund);
            abort_if($delivery->order->user_id === $actor->id, 403);
            if ($existing = $locked->transfer()->first()) {
                if ($existing->refund_destination_id !== $destinationId || $existing->destination_version !== $destinationVersion) {
                    $this->invalid('تم تجهيز التحويل بوسيلة مختلفة؛ افتح سجل التحويل الحالي.');
                }
                return $existing;
            }
            if ($locked->status !== RefundStatus::Approved || $locked->lock_version !== $version) {
                $this->invalid('يلزم استرداد معتمد ونسخة حديثة من الصفحة.');
            }
            $destination = $locked->activeDestination()->lockForUpdate()->first();
            if (! $destination || $destination->id !== $destinationId || $destination->lock_version !== $destinationVersion
                || $destination->status !== RefundDestinationStatus::Verified || ! $destination->reviewed_at) {
                $this->invalid('وسيلة الاستلام غير موثّقة أو تغيّرت؛ حدّث الصفحة.');
            }
            $sale = $this->review->assertFinancialSnapshot($locked, $delivery);
            $this->assertPaymentCapacity($sale->payment_id, $sale->payment->amount, $locked->amount);
            $snapshot = $locked->amount_snapshot;
            if ($locked->amount !== $snapshot['merchant_net_minor'] + $snapshot['commission_minor']
                + $snapshot['delivery_minor'] + $snapshot['service_minor']) {
                $this->invalid('تفصيل الاسترداد لا يطابق المبلغ الإجمالي.');
            }
            $transfer = $locked->transfer()->create([
                'active_key' => 'refund:'.$locked->id,
                'refund_destination_id' => $destination->id, 'destination_version' => $destination->lock_version,
                'payment_id' => $sale->payment_id, 'recipient_snapshot' => $destination->recipient_snapshot,
                'amount' => $locked->amount, 'currency' => $locked->currency,
                'merchant_amount' => $snapshot['merchant_net_minor'], 'commission_amount' => $snapshot['commission_minor'],
                'delivery_amount' => $snapshot['delivery_minor'], 'service_amount' => $snapshot['service_minor'],
                'prepared_by' => $actor->id, 'prepared_at' => now(),
            ]);
            $locked->update(['status' => RefundStatus::Processing, 'lock_version' => $locked->lock_version + 1]);
            $this->audit->record('refund.transfer_prepared', $transfer, null, ['refund_request_id' => $locked->id,
                'destination_id' => $destination->id, 'amount' => $transfer->amount, 'currency' => $transfer->currency]);

            return $transfer;
        }, 3);
    }

    public function recordPaid(RefundRequest $refund, User $actor, array $data, UploadedFile $proof): RefundTransfer
    {
        abort_unless($actor->hasPermission('refunds.pay'), 403);
        $reference = strtoupper(trim((string) ($data['transaction_reference'] ?? '')));
        $validated = Validator::make(array_replace($data, ['proof' => $proof, 'transaction_reference' => $reference]), self::receiptRules())->validate();
        $proofHash = hash_file('sha256', $proof->getRealPath());
        $storedPath = null;
        try {
            // No automatic retries around file writes: rollback cleanup must not orphan a retry's file.
            return DB::transaction(function () use ($refund, $actor, $validated, $reference, $proof, $proofHash, &$storedPath) {
                [$delivery, $locked] = $this->lockRefund($refund);
                abort_if($delivery->order->user_id === $actor->id, 403);
                $transfer = $locked->transfer()->lockForUpdate()->first();
                if (! $transfer || $transfer->id !== (int) $validated['transfer_id'] || $transfer->cancelled_at) {
                    $this->invalid('محاولة التحويل تغيّرت؛ افتح سجل المحاولة الحالية ولا تكرر الحوالة.');
                }
                $amount = $this->money->decimalToMinorUnits($validated['amount']);
                $transferredAt = Carbon::parse($validated['transferred_at'])->startOfSecond();
                if ($locked->status === RefundStatus::Paid && $transfer->paid_at) {
                    if ($transfer->transaction_reference !== $reference || $transfer->proof_sha256 !== $proofHash
                        || $transfer->amount !== $amount || ! $transfer->transferred_at->equalTo($transferredAt)) {
                        $this->invalid('سُجّل التحويل بالفعل ببيانات مختلفة. لا تُعد إرسال حوالة مالية.');
                    }
                    return $transfer;
                }
                if ($locked->status !== RefundStatus::Processing || $transfer->paid_at
                    || $locked->lock_version !== (int) $validated['lock_version']) {
                    $this->invalid('حالة التحويل تغيّرت؛ حدّث الصفحة ولا تكرر الحوالة خارج الموقع.');
                }
                if ($amount !== $transfer->amount || $amount !== $locked->amount || $transferredAt->lt($transfer->prepared_at->copy()->startOfSecond())) {
                    $this->invalid('المبلغ أو وقت الحوالة لا يطابقان التحويل المجهّز.');
                }
                $destination = RefundDestination::query()->whereKey($transfer->refund_destination_id)->lockForUpdate()->firstOrFail();
                if ($destination->active_key !== 'refund:'.$locked->id || $destination->status !== RefundDestinationStatus::Verified
                    || $destination->lock_version !== $transfer->destination_version || $destination->recipient_snapshot !== $transfer->recipient_snapshot) {
                    $this->invalid('تعارض في وجهة التحويل؛ تلزم مطابقة مالية.');
                }
                $sale = $this->review->assertFinancialSnapshot($locked, $delivery);
                $snapshot = $locked->amount_snapshot;
                if ($sale->payment_id !== $transfer->payment_id || $transfer->currency !== $locked->currency
                    || $transfer->merchant_amount !== $snapshot['merchant_net_minor'] || $transfer->commission_amount !== $snapshot['commission_minor']
                    || $transfer->delivery_amount !== $snapshot['delivery_minor'] || $transfer->service_amount !== $snapshot['service_minor']) {
                    $this->invalid('تعارض في مصدر المبلغ؛ تلزم مطابقة مالية.');
                }
                $this->assertPaymentCapacity($transfer->payment_id, $sale->payment->amount, $transfer->amount, $transfer->id);
                if (RefundTransfer::query()->where('transaction_reference', $reference)->orWhere('proof_sha256', $proofHash)->exists()) {
                    $this->invalid('مرجع الحوالة أو ملف الإثبات مستخدم لاسترداد آخر.');
                }
                $storedPath = $proof->storeAs('refund-proofs/'.$transfer->id, Str::uuid().'.'.$proof->guessExtension(), 'local');
                if (! $storedPath) {
                    throw new \RuntimeException('Refund proof could not be stored.');
                }
                $storedFile = Storage::disk('local')->path($storedPath);
                $storedSize = is_file($storedFile) ? filesize($storedFile) : false;
                $storedHash = is_file($storedFile) ? hash_file('sha256', $storedFile) : false;
                if (! is_int($storedSize) || $storedSize < 1 || ! is_string($storedHash)
                    || ! hash_equals($proofHash, $storedHash)) {
                    throw new \RuntimeException('Refund proof integrity verification failed.');
                }
                $this->ledger->refundHeldSale($delivery, $locked, $actor);
                $transfer->update(['paid_by' => $actor->id, 'paid_at' => now(), 'transferred_at' => $transferredAt,
                    'transaction_reference' => $reference, 'proof_path' => $storedPath,
                    'proof_mime' => $proof->getMimeType(), 'proof_size' => $storedSize, 'proof_sha256' => $storedHash]);
                $locked->update(['status' => RefundStatus::Paid, 'lock_version' => $locked->lock_version + 1]);
                $delivery->dispute->update(['status' => 'refunded', 'closed_by' => $actor->id, 'closed_at' => now(),
                    'close_reason' => 'تم تسجيل الاسترداد الكامل وإثبات الحوالة؛ لا تعاد مستحقات هذا الطلب للتاجر.']);
                $this->audit->record('refund.paid', $locked, ['status' => RefundStatus::Processing->value], [
                    'status' => RefundStatus::Paid->value, 'transfer_id' => $transfer->id, 'amount' => $transfer->amount,
                    'currency' => $transfer->currency, 'merchant_amount' => $transfer->merchant_amount,
                    'commission_amount' => $transfer->commission_amount, 'delivery_amount' => $transfer->delivery_amount,
                    'service_amount' => $transfer->service_amount,
                ]);

                return $transfer;
            });
        } catch (Throwable $exception) {
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }
            if ($exception instanceof QueryException && RefundTransfer::query()
                ->where('transaction_reference', $reference)->orWhere('proof_sha256', $proofHash)->exists()) {
                $this->invalid('مرجع الحوالة أو ملف الإثبات مستخدم مسبقًا. حدّث الصفحة ولا تكرر الحوالة.');
            }
            throw $exception;
        }
    }

    public function cancelPreparation(RefundTransfer $transfer, User $actor, array $data): RefundTransfer
    {
        abort_unless($actor->hasPermission('refunds.pay') && $actor->hasPermission('refunds.cancel'), 403);
        $validated = Validator::make($data, self::cancellationRules())->validate();

        return DB::transaction(function () use ($transfer, $actor, $validated) {
            [$delivery, $locked] = $this->lockRefund($transfer->refund);
            abort_if($delivery->order->user_id === $actor->id, 403);
            $attempt = $locked->transfers()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            if ($attempt->cancelled_at) {
                // Retrying an old cancellation must never affect a newer attempt.
                return $attempt;
            }
            if ($locked->status !== RefundStatus::Processing || $locked->lock_version !== (int) $validated['lock_version']
                || $attempt->active_key !== 'refund:'.$locked->id) {
                $this->invalid('تغيّرت حالة المحاولة؛ حدّث الصفحة قبل إلغاء التجهيز.');
            }
            foreach (['paid_at', 'paid_by', 'transferred_at', 'transaction_reference', 'proof_path', 'proof_mime', 'proof_size', 'proof_sha256'] as $field) {
                if ($attempt->getAttribute($field) !== null) {
                    $this->invalid('توجد بيانات تنفيذ أو إثبات؛ لا يمكن إلغاء التجهيز وتلزم مراجعة مالية.');
                }
            }
            $this->review->assertFinancialSnapshot($locked, $delivery);
            if (\App\Models\LedgerEntry::query()->whereIn('idempotency_key', [
                'refund:'.$locked->id.':held:debit', 'refund:'.$locked->id.':refunded:credit',
            ])->exists()) {
                $this->invalid('توجد قيود استرداد؛ تلزم مراجعة مالية قبل أي محاولة جديدة.');
            }
            $attempt->update(['active_key' => null, 'cancelled_by' => $actor->id, 'cancelled_at' => now(),
                'cancellation_reason' => $validated['cancellation_reason']]);
            $locked->update(['status' => RefundStatus::Approved, 'lock_version' => $locked->lock_version + 1]);
            $this->audit->record('refund.transfer_cancelled', $attempt, ['status' => 'prepared'], [
                'status' => 'cancelled', 'refund_request_id' => $locked->id, 'not_sent_confirmed' => true,
            ]);

            return $attempt;
        }, 3);
    }

    public static function cancellationRules(): array
    {
        return ['lock_version' => ['required', 'integer', 'min:0'],
            'not_sent_confirmed' => ['required', 'accepted'],
            'cancellation_reason' => ['required', 'string', 'min:5', 'max:2000']];
    }

    public static function receiptRules(): array
    {
        return ['amount' => ['required', 'regex:/\A[0-9]{1,10}(\.[0-9]{1,2})?\z/'],
            'transaction_reference' => ['required', 'string', 'min:3', 'max:150', 'regex:/\A[A-Za-z0-9][A-Za-z0-9._\/-]*\z/'],
            'transferred_at' => ['required', 'date', 'before_or_equal:now'],
            'transfer_confirmed' => ['required', 'accepted'],
            'transfer_id' => ['required', 'integer', 'min:1'],
            'lock_version' => ['required', 'integer', 'min:0'],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240']];
    }

    private function lockRefund(RefundRequest $refund): array
    {
        $deliveryId = $refund->dispute->delivery_id;
        $orderId = $refund->dispute->delivery->order_id;
        // Serialize the aggregate refund budget across sibling suborders of one payment.
        Order::query()->whereKey($orderId)->lockForUpdate()->firstOrFail();
        $delivery = Delivery::query()->whereKey($deliveryId)->lockForUpdate()->firstOrFail();

        return [$delivery, RefundRequest::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail()];
    }

    private function assertPaymentCapacity(int $paymentId, int $received, int $amount, ?int $exclude = null): void
    {
        $reserved = (int) RefundTransfer::query()->where('payment_id', $paymentId)
            ->whereNull('cancelled_at')
            ->when($exclude, fn ($query) => $query->whereKeyNot($exclude))->sum('amount');
        if ($amount <= 0 || $reserved + $amount > $received) {
            $this->invalid('إجمالي الاستردادات المجهّزة والمنفّذة يتجاوز الدفعة المقبولة.');
        }
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['refund' => $message]);
    }
}

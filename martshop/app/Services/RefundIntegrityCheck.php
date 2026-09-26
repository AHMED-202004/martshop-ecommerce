<?php

namespace App\Services;

use App\Models\{RefundRequest, User};
use Illuminate\Support\Facades\{DB, Storage};

class RefundIntegrityCheck
{
    public function inspect(RefundRequest $refund, User $actor): array
    {
        abort_unless($actor->hasPermission('refunds.pay'), 403);

        // Read a database snapshot without taking action locks or writing audit/financial rows.
        return DB::transaction(function () use ($refund) {
            // Raw values deliberately avoid decrypting recipients or casting a corrupted status enum.
            $row = DB::table('refund_requests')->where('id', $refund->id)->first();
            abort_unless($row, 404);
            $issues = [];
            $add = function (string $code, string $message, ?int $attemptId = null) use (&$issues) {
                $issues[] = ['code' => $code, 'message' => $message, 'attempt_id' => $attemptId];
            };
            $snapshot = json_decode($row->amount_snapshot, true);
            $parts = ['product_minor', 'merchant_net_minor', 'commission_minor', 'delivery_minor', 'service_minor', 'total_minor'];
            $validSnapshot = is_array($snapshot);
            foreach ($parts as $part) {
                $validSnapshot = $validSnapshot && isset($snapshot[$part]) && is_int($snapshot[$part]) && $snapshot[$part] >= 0;
            }
            if (! $validSnapshot || $snapshot['total_minor'] !== (int) $row->amount
                || $snapshot['product_minor'] !== $snapshot['merchant_net_minor'] + $snapshot['commission_minor']
                || $snapshot['total_minor'] !== $snapshot['product_minor'] + $snapshot['delivery_minor'] + $snapshot['service_minor']) {
                $add('refund_amount', 'تفصيل مبلغ الاسترداد مفقود أو غير متطابق مع الإجمالي.');
            }

            $sale = DB::table('ledger_entries')->where('id', $row->sale_entry_id)->first();
            $dispute = DB::table('delivery_disputes')->where('id', $row->delivery_dispute_id)->first();
            $delivery = $dispute ? DB::table('deliveries')->where('id', $dispute->delivery_id)->first() : null;
            $payment = $sale ? DB::table('payments')->where('id', $sale->payment_id)->first() : null;
            if (! $sale || ! $delivery || ! $payment || $sale->entry_type !== 'sale' || $sale->direction !== 'credit'
                || $sale->currency !== $row->currency || $payment->currency !== $row->currency || $payment->status !== 'accepted'
                || $sale->order_id !== $delivery->order_id || $sale->merchant_order_id !== $delivery->merchant_order_id
                || $payment->order_id !== $delivery->order_id || (int) $row->amount <= 0 || (int) $row->amount > (int) $payment->amount
                || ($validSnapshot && ((int) $sale->net_amount !== $snapshot['merchant_net_minor']
                    || (int) $sale->gross_amount !== $snapshot['product_minor']
                    || (int) $sale->commission_amount !== $snapshot['commission_minor'] || (int) $sale->fee_amount !== 0))) {
                $add('refund_source', 'قيد البيع أو الدفعة المقبولة أو ارتباط الطلب ومبلغ الاسترداد غير متطابق.');
            }
            $paid = $row->status === 'paid';
            if (! in_array($row->status, ['requested', 'approved', 'rejected', 'processing', 'paid'], true)) {
                $add('refund_status', 'حالة الاسترداد غير معروفة.');
            }
            if (! $dispute || ($paid && $dispute->status !== 'refunded')
                || (in_array($row->status, ['requested', 'approved', 'processing'], true) && $dispute->status !== 'open')
                || ($row->status !== 'rejected' && $delivery?->settled_at)) {
                $add('dispute_state', 'حالة النزاع أو تحرير مستحقات الطلب لا تتوافق مع الاسترداد.');
            }

            $attempts = DB::table('refund_transfers')->where('refund_request_id', $row->id)
                ->select(['id', 'active_key', 'cancelled_at', 'paid_at', 'paid_by', 'transferred_at', 'transaction_reference',
                    'proof_path', 'proof_mime', 'proof_size', 'proof_sha256', 'amount', 'currency', 'payment_id',
                    'merchant_amount', 'commission_amount', 'delivery_amount', 'service_amount'])->get();
            $current = $attempts->whereNull('cancelled_at');
            $expectedCurrent = in_array($row->status, ['processing', 'paid'], true) ? 1 : 0;
            if ($current->count() !== $expectedCurrent) {
                $add('attempt_count', 'عدد محاولات التحويل الحالية لا يتوافق مع حالة الاسترداد.');
            }
            foreach ($attempts as $attempt) {
                if ($attempt->active_key !== ($attempt->cancelled_at ? null : 'refund:'.$row->id)) {
                    $add('attempt_key', 'ربط المحاولة الحالية أو الملغاة غير متطابق.', $attempt->id);
                }
                $hasReceipt = false;
                foreach (['paid_at', 'paid_by', 'transferred_at', 'transaction_reference', 'proof_path', 'proof_mime', 'proof_size', 'proof_sha256'] as $field) {
                    $hasReceipt = $hasReceipt || $attempt->{$field} !== null;
                }
                if (($attempt->cancelled_at && $hasReceipt) || (! $attempt->cancelled_at && (bool) $attempt->paid_at !== $paid)
                    || (! $attempt->paid_at && $hasReceipt)) {
                    $add('attempt_state', 'حالة المحاولة أو بيانات التنفيذ الجزئية تتعارض مع حالة الاسترداد.', $attempt->id);
                }
                $matches = (int) $attempt->amount === (int) $row->amount && $attempt->currency === $row->currency
                    && $sale && $attempt->payment_id === $sale->payment_id;
                foreach (['merchant_amount' => 'merchant_net_minor', 'commission_amount' => 'commission_minor',
                    'delivery_amount' => 'delivery_minor', 'service_amount' => 'service_minor'] as $field => $part) {
                    $matches = $matches && $validSnapshot && (int) $attempt->{$field} === $snapshot[$part];
                }
                if (! $matches) {
                    $add('attempt_amount', 'مبلغ المحاولة أو عملتها أو مكونات المبلغ أو مصدر الدفع غير متطابقة.', $attempt->id);
                }
                if ($attempt->paid_at) {
                    $problem = $this->proofProblem($attempt);
                    if ($problem) {
                        $add($problem, 'إثبات الدفع مفقود أو ناقص أو تعذّر التحقق من سلامة ملفه؛ تلزم مراجعة خاصة.', $attempt->id);
                    }
                }
            }

            $keys = ['held' => 'refund:'.$row->id.':held:debit', 'refunded' => 'refund:'.$row->id.':refunded:credit'];
            $entries = DB::table('ledger_entries')->where(function ($query) use ($keys, $sale) {
                $query->whereIn('idempotency_key', array_values($keys));
                if ($sale) {
                    $query->orWhere(fn ($q) => $q->where('merchant_order_id', $sale->merchant_order_id)->where('entry_type', 'refund'));
                }
            })->get();
            if ($entries->count() !== ($paid ? 2 : 0)) {
                $add('ledger_count', 'عدد قيود الاسترداد لا يتوافق مع الحالة؛ قد توجد قيود مفقودة أو إضافية.');
            }
            if ($paid && $sale) {
                foreach ($keys as $bucket => $key) {
                    $entry = $entries->firstWhere('idempotency_key', $key);
                    $expectedAmount = (int) $sale->net_amount * ($bucket === 'held' ? -1 : 1);
                    if (! $entry || $entry->entry_type !== 'refund' || $entry->status !== $bucket
                        || $entry->direction !== ($bucket === 'held' ? 'debit' : 'credit')
                        || (int) $entry->amount !== $expectedAmount || (int) $entry->net_amount !== $expectedAmount
                        || $entry->currency !== $row->currency || $entry->merchant_id !== $sale->merchant_id
                        || $entry->order_id !== $sale->order_id || $entry->merchant_order_id !== $sale->merchant_order_id
                        || $entry->payment_id !== $sale->payment_id) {
                        $add('ledger_'.$bucket, 'قيد الاسترداد في خانة '.$bucket.' مفقود أو لا يطابق صافي التاجر وارتباط الطلب.');
                    }
                }
            }
            if ($payment && (int) DB::table('refund_transfers')->where('payment_id', $payment->id)
                ->whereNull('cancelled_at')->sum('amount') > (int) $payment->amount) {
                $add('payment_capacity', 'إجمالي محاولات الاسترداد غير الملغاة يتجاوز الدفعة المقبولة.');
            }

            return ['reference' => $row->reference, 'checked_at' => now(), 'issues' => $issues,
                'attempt_count' => $attempts->count(), 'ledger_count' => $entries->count()];
        });
    }

    private function proofProblem(object $attempt): ?string
    {
        if (! $attempt->transferred_at || ! $attempt->transaction_reference || ! $attempt->proof_mime
            || ! is_string($attempt->proof_sha256) || ! preg_match('/\A[a-f0-9]{64}\z/', $attempt->proof_sha256)
            || (int) $attempt->proof_size <= 0 || (int) $attempt->proof_size > 10240 * 1024) {
            return 'proof_metadata';
        }
        // Do not follow arbitrary paths from a damaged database, including directory traversal.
        if (! is_string($attempt->proof_path) || ! preg_match('/\Arefund-proofs\/'.$attempt->id.'\/[a-zA-Z0-9-]+\.(pdf|jpg|jpeg|png|webp)\z/', $attempt->proof_path)) {
            return 'proof_path';
        }
        try {
            $disk = Storage::disk('local');
            $root = realpath($disk->path(''));
            $path = realpath($disk->path($attempt->proof_path));
            if (! $root || ! $path || ! is_file($path)) {
                return 'proof_missing';
            }
            $prefix = rtrim(str_replace('\\', '/', $root), '/').'/refund-proofs/'.$attempt->id.'/';
            $normalizedPath = str_replace('\\', '/', $path);
            if (DIRECTORY_SEPARATOR === '\\') {
                $prefix = strtolower($prefix);
                $normalizedPath = strtolower($normalizedPath);
            }
            if (! str_starts_with($normalizedPath, $prefix)) {
                return 'proof_path';
            }
            if (filesize($path) !== (int) $attempt->proof_size || ! hash_equals($attempt->proof_sha256, hash_file('sha256', $path))) {
                return 'proof_integrity';
            }
        } catch (\Throwable) {
            return 'proof_unreadable';
        }

        return null;
    }
}

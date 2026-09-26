<?php

namespace App\Services;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentProof;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class PaymentSubmissionService
{
    public function __construct(
        private readonly MarketplaceSettings $money,
        private readonly AuditLogger $audit,
    ) {}

    public function submit(
        Order $order,
        User $user,
        array $data,
        UploadedFile $proof,
    ): Payment {
        $data = Validator::make(array_replace($data, ['proof' => $proof]), self::submissionRules())->validate();
        $idempotencyKey = (string) $data['idempotency_key'];
        if ($existing = $this->existing($user, $idempotencyKey)) {
            return $existing;
        }

        $storedPath = null;
        $providerReference = mb_strtoupper(trim((string) $data['reference_number']));

        try {
            return DB::transaction(function () use (
                $order, $user, $data, $proof, $idempotencyKey, $providerReference, &$storedPath
            ) {
                $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
                if ($lockedOrder->user_id !== $user->id) {
                    abort(403);
                }
                if ($lockedOrder->status !== OrderStatus::Confirmed) {
                    throw ValidationException::withMessages([
                        'payment' => 'يجب اكتمال تأكيد جميع التجار قبل إرسال التحويل.',
                    ]);
                }
                if ($lockedOrder->payment_status === OrderPaymentStatus::Paid) {
                    throw ValidationException::withMessages(['payment' => 'هذا الطلب مدفوع بالفعل.']);
                }

                $method = PaymentMethod::query()
                    ->active()
                    ->whereKey((int) $data['payment_method_id'])
                    ->lockForUpdate()
                    ->first();
                if (! $method) {
                    throw ValidationException::withMessages(['payment_method_id' => 'وسيلة الدفع غير متاحة حاليًا.']);
                }

                if ($existing = Payment::query()
                    ->where('user_id', $user->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first()) {
                    return $existing;
                }
                if (Payment::query()->where('open_key', 'order:'.$lockedOrder->id)->exists()) {
                    throw ValidationException::withMessages(['payment' => 'يوجد إثبات دفع قيد المراجعة لهذا الطلب.']);
                }
                if (Payment::query()
                    ->where('provider', $method->slug)
                    ->where('provider_ref', $providerReference)
                    ->exists()) {
                    throw ValidationException::withMessages(['reference_number' => 'رقم التحويل مستخدم مسبقًا.']);
                }

                $amount = $this->money->decimalToMinorUnits($data['amount']);
                $expectedAmount = $this->money->decimalToMinorUnits($lockedOrder->total);
                $payment = Payment::create([
                    'order_id' => $lockedOrder->id,
                    'user_id' => $user->id,
                    'payment_method_id' => $method->id,
                    'open_key' => 'order:'.$lockedOrder->id,
                    'idempotency_key' => $idempotencyKey,
                    'order_no' => 'ORDER-'.$lockedOrder->id,
                    'amount' => $amount,
                    'currency' => $lockedOrder->currency,
                    'provider' => $method->slug,
                    'provider_ref' => $providerReference,
                    'sender_name' => trim($data['sender_name']),
                    'sender_account' => trim($data['sender_account']),
                    'transferred_at' => $data['transferred_at'],
                    'status' => PaymentStatus::Pending,
                    'meta' => [
                        'expected_amount_minor' => $expectedAmount,
                        'expected_amount' => $this->money->minorUnitsToDecimal($expectedAmount),
                        'payment_method' => [
                            'id' => $method->id,
                            'name' => $method->name,
                            'slug' => $method->slug,
                            'type' => $method->type,
                            'account_name' => $method->account_name,
                            'account_identifier' => $method->account_identifier,
                            'instructions' => $method->instructions,
                        ],
                    ],
                ]);

                $extension = $proof->guessExtension() ?: 'bin';
                $fileName = Str::uuid().'.'.$extension;
                $storedPath = $proof->storeAs('payment-proofs/'.$payment->id, $fileName, 'local');
                if (! $storedPath) {
                    throw new RuntimeException('Payment proof could not be stored.');
                }
                $storedFile = Storage::disk('local')->path($storedPath);
                $storedSize = is_file($storedFile) ? filesize($storedFile) : false;
                $storedHash = is_file($storedFile) ? hash_file('sha256', $storedFile) : false;
                $sourceHash = hash_file('sha256', $proof->getRealPath());
                if (! is_int($storedSize) || $storedSize < 1 || ! is_string($storedHash)
                    || ! is_string($sourceHash) || ! hash_equals($sourceHash, $storedHash)) {
                    throw new RuntimeException('Payment proof integrity verification failed.');
                }

                PaymentProof::create([
                    'payment_id' => $payment->id,
                    'disk' => 'local',
                    'path' => $storedPath,
                    'original_name' => $proof->getClientOriginalName(),
                    'mime_type' => $proof->getMimeType(),
                    'size' => $storedSize,
                    'sha256' => $storedHash,
                    'created_at' => now(),
                ]);

                $lockedOrder->update([
                    'payment_method' => $method->slug,
                    'payment_status' => OrderPaymentStatus::PendingReview,
                ]);
                $this->audit->record('payment.submitted', $payment, null, [
                    'order_id' => $lockedOrder->id,
                    'amount' => $amount,
                    'currency' => $lockedOrder->currency,
                    'provider' => $method->slug,
                    'status' => PaymentStatus::Pending->value,
                ]);

                return $payment->load(['order', 'method', 'proof']);
            }, 3);
        } catch (QueryException $exception) {
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }
            if ($existing = $this->existing($user, $idempotencyKey)) {
                return $existing;
            }
            if (Payment::query()->where('provider_ref', $providerReference)->exists()) {
                throw ValidationException::withMessages(['reference_number' => 'رقم التحويل مستخدم مسبقًا.']);
            }

            throw $exception;
        } catch (Throwable $exception) {
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }
            throw $exception;
        }
    }

    private function existing(User $user, string $idempotencyKey): ?Payment
    {
        return Payment::query()
            ->where('user_id', $user->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    public static function submissionRules(): array
    {
        return [
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'reference_number' => ['required', 'string', 'min:3', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999.99'],
            'transferred_at' => ['required', 'date', 'before_or_equal:now'],
            'sender_name' => ['required', 'string', 'min:2', 'max:150'],
            'sender_account' => ['required', 'string', 'min:3', 'max:100'],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}

<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\DeliveryEvent;
use App\Models\DeliveryProof;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class DeliveryConfirmationService
{
    public function __construct(
        private readonly MarketplaceSettings $settings,
        private readonly AuditLogger $audit,
        private readonly DeliveryWorkerAvailabilityService $availability,
    ) {}

    public function markPickedUp(Delivery $delivery, User $worker, int $lockVersion, ?string $note): Delivery
    {
        return $this->transition(
            $delivery, $worker, $lockVersion, DeliveryStatus::Accepted,
            DeliveryStatus::PickedUp, 'picked_up', 'picked_up_at', $note, 'merchant_arrived_at',
        );
    }

    public function recordMilestone(Delivery $delivery, User $worker, int $lockVersion, string $milestone, ?string $note): Delivery
    {
        $definitions = [
            'heading-to-merchant' => [DeliveryStatus::Accepted, 'heading_to_merchant_at', null],
            'merchant-arrived' => [DeliveryStatus::Accepted, 'merchant_arrived_at', 'heading_to_merchant_at'],
            'out-for-delivery' => [DeliveryStatus::InTransit, 'out_for_delivery_at', null],
            'customer-arrived' => [DeliveryStatus::InTransit, 'customer_arrived_at', 'out_for_delivery_at'],
        ];
        abort_unless(isset($definitions[$milestone]), 404);
        [$requiredStatus, $column, $prerequisite] = $definitions[$milestone];

        return DB::transaction(function () use ($delivery, $worker, $lockVersion, $milestone, $note, $requiredStatus, $column, $prerequisite) {
            $locked = Delivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            $this->ensureOwner($locked, $worker, 'deliveries.update-status');
            if ($locked->{$column}) return $locked;
            $this->ensureTransition($locked, $lockVersion, $requiredStatus);
            if ($prerequisite && ! $locked->{$prerequisite}) throw ValidationException::withMessages(['delivery' => 'يجب تسجيل المرحلة السابقة أولًا.']);
            $locked->update([$column => now(), 'delivery_note' => $note ?: $locked->delivery_note, 'lock_version' => $locked->lock_version + 1]);
            $this->event($locked, str_replace('-', '_', $milestone), $requiredStatus, $requiredStatus, $worker, $note);
            $this->audit->record('delivery.'.str_replace('-', '_', $milestone), $locked, null, [$column => $locked->{$column}->toIso8601String(), 'lock_version' => $locked->lock_version], $note);
            $this->availability->touch($worker);
            return $locked->refresh();
        }, 3);
    }

    public function markInTransit(Delivery $delivery, User $worker, int $lockVersion, ?string $note): Delivery
    {
        return $this->transition(
            $delivery, $worker, $lockVersion, DeliveryStatus::PickedUp,
            DeliveryStatus::InTransit, 'in_transit', 'in_transit_at', $note,
        );
    }

    public function confirmDelivered(
        Delivery $delivery,
        User $worker,
        int $lockVersion,
        string $pin,
        ?UploadedFile $proof,
        ?string $note,
    ): Delivery {
        $storedPath = null;

        try {
            return DB::transaction(function () use (
                $delivery, $worker, $lockVersion, $pin, $proof, $note, &$storedPath,
            ) {
                $locked = Delivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();
                $this->ensureOwner($locked, $worker, 'deliveries.confirm');
                if ($locked->status === DeliveryStatus::Delivered) {
                    return $locked;
                }
                Validator::make([
                    'lock_version' => $lockVersion,
                    'pin' => $pin,
                    'proof' => $proof,
                    'note' => $note,
                ], self::confirmationRules())->validate();
                $this->ensureTransition($locked, $lockVersion, DeliveryStatus::InTransit);
                if ($locked->expected_delivery_at && ! $locked->customer_arrived_at) {
                    throw ValidationException::withMessages(['delivery' => 'سجّل الوصول إلى العميل قبل تأكيد التسليم.']);
                }
                if (! $locked->confirmation_pin_hash || ! Hash::check($pin, $locked->confirmation_pin_hash)) {
                    throw ValidationException::withMessages(['pin' => 'رمز تأكيد التسليم غير صحيح.']);
                }

                $proofHash = null;
                if ($proof) {
                    $extension = strtolower($proof->guessExtension() ?: 'bin');
                    $fileName = Str::ulid().'.'.$extension;
                    $storedPath = $proof->storeAs('delivery-proofs/'.$locked->id, $fileName, 'local');
                    if (! $storedPath) throw ValidationException::withMessages(['proof' => 'تعذر حفظ إثبات التسليم.']);
                    $storedFile = Storage::disk('local')->path($storedPath);
                    $proofSize = is_file($storedFile) ? filesize($storedFile) : false;
                    $proofHash = is_file($storedFile) ? hash_file('sha256', $storedFile) : false;
                    if (! is_int($proofSize) || ! is_string($proofHash)) throw ValidationException::withMessages(['proof' => 'تعذر التحقق من سلامة إثبات التسليم.']);
                    DeliveryProof::query()->create([
                        'delivery_id' => $locked->id, 'disk' => 'local', 'path' => $storedPath,
                        'original_name' => $proof->getClientOriginalName(), 'mime_type' => $proof->getMimeType() ?: 'application/octet-stream',
                        'size' => $proofSize, 'sha256' => $proofHash, 'uploaded_by' => $worker->id, 'created_at' => now(),
                    ]);
                }

                $from = $locked->status;
                $holdDays = $this->settings->integer('settlement.dispute_days');
                if ($holdDays < 1 || $holdDays > 90) {
                    throw ValidationException::withMessages(['delivery' => 'مهلة النزاع غير صالحة؛ يلزم مراجعة الإعدادات.']);
                }
                $confirmedAt = now();
                $locked->update([
                    'status' => DeliveryStatus::Delivered,
                    'delivered_at' => $confirmedAt,
                    'settlement_hold_days' => $holdDays,
                    'settlement_due_at' => $confirmedAt->copy()->addDays($holdDays),
                    'delivery_note' => $note,
                    'lock_version' => $locked->lock_version + 1,
                ]);
                $this->event($locked, 'delivered', $from, DeliveryStatus::Delivered, $worker, $note, [
                    'proof_attached' => $proofHash !== null,
                ]);
                $this->audit->record('delivery.delivered', $locked, [
                    'status' => $from->value,
                ], [
                    'status' => DeliveryStatus::Delivered->value,
                    'delivered_at' => $locked->delivered_at?->toIso8601String(),
                    'lock_version' => $locked->lock_version,
                    'settlement_due_at' => $locked->settlement_due_at->toIso8601String(),
                ], $note);
                $this->availability->touch($worker);

                return $locked->refresh();
            }); // Do not retry a transaction containing filesystem writes automatically.
        } catch (Throwable $exception) {
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }
            throw $exception;
        }
    }

    public static function confirmationRules(): array
    {
        return [
            'lock_version' => ['required', 'integer', 'min:0'],
            'pin' => ['required', 'digits:4'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:10240'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function transition(
        Delivery $delivery,
        User $worker,
        int $lockVersion,
        DeliveryStatus $from,
        DeliveryStatus $to,
        string $eventType,
        string $timestampColumn,
        ?string $note,
        ?string $requiredMilestone = null,
    ): Delivery {
        return DB::transaction(function () use (
            $delivery, $worker, $lockVersion, $from, $to, $eventType, $timestampColumn, $note, $requiredMilestone,
        ) {
            $locked = Delivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            $this->ensureOwner($locked, $worker, 'deliveries.update-status');
            if ($locked->status === $to) {
                return $locked;
            }
            $this->ensureTransition($locked, $lockVersion, $from);
            if ($requiredMilestone && $locked->expected_delivery_at && ! $locked->{$requiredMilestone}) {
                throw ValidationException::withMessages(['delivery' => 'يجب تسجيل مراحل الوصول السابقة أولًا.']);
            }
            $locked->update([
                'status' => $to,
                $timestampColumn => now(),
                'delivery_note' => $note ?: $locked->delivery_note,
                'lock_version' => $locked->lock_version + 1,
            ]);
            $this->event($locked, $eventType, $from, $to, $worker, $note);
            $this->audit->record('delivery.'.$eventType, $locked, ['status' => $from->value], [
                'status' => $to->value,
                'lock_version' => $locked->lock_version,
            ], $note);
            $this->availability->touch($worker);

            return $locked->refresh();
        }, 3);
    }

    private function ensureOwner(Delivery $delivery, User $worker, string $permission): void
    {
        if ($delivery->delivery_worker_id !== $worker->id
            || ! $worker->hasRole('delivery-worker')
            || ! $worker->hasPermission($permission)) {
            abort(403);
        }
    }

    private function ensureTransition(Delivery $delivery, int $lockVersion, DeliveryStatus $required): void
    {
        if ($delivery->status !== $required) {
            throw ValidationException::withMessages(['delivery' => 'انتقال حالة التوصيل غير مسموح.']);
        }
        if ($delivery->lock_version !== $lockVersion) {
            throw ValidationException::withMessages(['lock_version' => 'تم تحديث المهمة؛ حدّث الصفحة.']);
        }
    }

    private function event(
        Delivery $delivery,
        string $eventType,
        DeliveryStatus $from,
        DeliveryStatus $to,
        User $actor,
        ?string $note,
        ?array $metadata = null,
    ): void {
        DeliveryEvent::query()->create([
            'delivery_id' => $delivery->id,
            'event_type' => $eventType,
            'from_status' => $from->value,
            'to_status' => $to->value,
            'actor_id' => $actor->id,
            'note' => $note,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}

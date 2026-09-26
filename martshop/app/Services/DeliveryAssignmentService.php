<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\DeliveryWorkerAvailability;
use App\Enums\MerchantOrderStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Models\Delivery;
use App\Models\DeliveryEvent;
use App\Models\MerchantOrder;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DeliveryAssignmentService
{
    public function __construct(private readonly AuditLogger $audit, private readonly DeliverySlaResolver $sla, private readonly DeliveryWorkerAvailabilityService $availability) {}

    public function assign(
        MerchantOrder $merchantOrder,
        User $worker,
        User $actor,
        ?string $note,
        int $lockVersion,
        ?string $expectedDeliveryAt = null,
        string $orderType = 'standard',
        string $deliveryMethod = 'courier',
        ?float $distanceKm = null,
    ): Delivery {
        abort_unless($actor->hasPermission('deliveries.manage'), 403);

        return DB::transaction(function () use ($merchantOrder, $worker, $actor, $note, $lockVersion, $expectedDeliveryAt, $orderType, $deliveryMethod, $distanceKm) {
            $order = Order::query()->whereKey($merchantOrder->order_id)->lockForUpdate()->firstOrFail();
            $lockedMerchantOrder = MerchantOrder::query()
                ->whereKey($merchantOrder->id)->lockForUpdate()->firstOrFail();
            $this->validateOrder($order, $lockedMerchantOrder);
            $destination = $order->delivery_address_snapshot ?? [];
            $destinationArea = trim((string) ($destination['city'] ?? $destination['governorate'] ?? ''));
            $slaRule = $this->sla->resolve($lockedMerchantOrder->origin_location_id, $destinationArea, $orderType, $deliveryMethod, $distanceKm);
            $deadline = $slaRule ? now()->addMinutes($slaRule->target_minutes) : $expectedDeliveryAt;

            $lockedWorker = User::query()->whereKey($worker->id)->lockForUpdate()->firstOrFail();
            if (! $lockedWorker->hasRole('delivery-worker')
                || ! $lockedWorker->hasPermission('deliveries.accept')) {
                throw ValidationException::withMessages([
                    'delivery_worker_id' => 'الحساب المحدد ليس عامل توصيل معتمدًا.',
                ]);
            }
            $workerAvailability = $lockedWorker->deliveryWorkerProfile?->availability ?? DeliveryWorkerAvailability::Available;
            if (! $workerAvailability->acceptsAssignments()) {
                throw ValidationException::withMessages(['delivery_worker_id' => 'عامل التوصيل غير متاح لاستقبال مهام جديدة.']);
            }
            $locationScope = $lockedWorker->staffLocations()->pluck('locations.id');
            if ($locationScope->isNotEmpty()
                && ($lockedMerchantOrder->origin_location_id === null
                    || ! $locationScope->contains((int) $lockedMerchantOrder->origin_location_id))) {
                throw ValidationException::withMessages([
                    'delivery_worker_id' => 'موقع استلام الطلب خارج نطاق عامل التوصيل المحدد.',
                ]);
            }

            $existing = Delivery::query()
                ->where('merchant_order_id', $lockedMerchantOrder->id)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                if ($existing->delivery_worker_id === $lockedWorker->id) {
                    return $existing;
                }
                if (! in_array($existing->status, [DeliveryStatus::Assigned, DeliveryStatus::Unassigned], true)) {
                    throw ValidationException::withMessages([
                        'delivery' => 'لا يمكن إعادة إسناد مهمة بعد قبولها من عامل التوصيل.',
                    ]);
                }
                if ($existing->lock_version !== $lockVersion) {
                    throw ValidationException::withMessages([
                        'lock_version' => 'تم تحديث مهمة التوصيل؛ حدّث الصفحة.',
                    ]);
                }

                $oldWorkerId = $existing->delivery_worker_id;
                $fromStatus = $existing->status;
                $existing->update([
                    'delivery_worker_id' => $lockedWorker->id,
                    'status' => DeliveryStatus::Assigned,
                    'assignment_notes' => $note,
                    'assigned_by' => $actor->id,
                    'assigned_at' => now(),
                    'expected_delivery_at' => $deadline,
                    'sla_rule_id' => $slaRule?->id,
                    'order_type' => $orderType,
                    'delivery_method' => $deliveryMethod,
                    'distance_km' => $distanceKm,
                    'accepted_at' => null,
                    'lock_version' => $existing->lock_version + 1,
                ]);
                $this->event($existing, $fromStatus === DeliveryStatus::Unassigned ? 'assigned' : 'reassigned', $fromStatus, DeliveryStatus::Assigned, $actor, $note, [
                    'previous_worker_id' => $oldWorkerId,
                    'delivery_worker_id' => $lockedWorker->id,
                ]);
                $this->audit->record('delivery.reassigned', $existing, [
                    'delivery_worker_id' => $oldWorkerId,
                ], [
                    'delivery_worker_id' => $lockedWorker->id,
                    'status' => DeliveryStatus::Assigned->value,
                    'lock_version' => $existing->lock_version,
                ], $note);

                return $existing->refresh();
            }

            if ($lockVersion !== 0) {
                throw ValidationException::withMessages(['lock_version' => 'قيمة نسخة الإسناد غير صالحة.']);
            }
            $pin = (string) random_int(1000, 9999);
            $delivery = Delivery::query()->create([
                'order_id' => $order->id,
                'merchant_order_id' => $lockedMerchantOrder->id,
                'delivery_worker_id' => $lockedWorker->id,
                'status' => DeliveryStatus::Assigned,
                'reference' => 'DLV-'.Str::upper((string) Str::ulid()),
                'assignment_key' => 'delivery:merchant-order:'.$lockedMerchantOrder->id,
                'origin_snapshot' => $this->originSnapshot($lockedMerchantOrder),
                'destination_snapshot' => $order->delivery_address_snapshot,
                'assignment_notes' => $note,
                'confirmation_pin' => $pin,
                'confirmation_pin_hash' => Hash::make($pin),
                'assigned_by' => $actor->id,
                'assigned_at' => now(),
                'expected_delivery_at' => $deadline,
                'sla_rule_id' => $slaRule?->id,
                'order_type' => $orderType,
                'delivery_method' => $deliveryMethod,
                'distance_km' => $distanceKm,
            ]);
            $this->event($delivery, 'assigned', null, DeliveryStatus::Assigned, $actor, $note, [
                'delivery_worker_id' => $lockedWorker->id,
            ]);
            $this->audit->record('delivery.assigned', $delivery, null, [
                'order_id' => $order->id,
                'merchant_order_id' => $lockedMerchantOrder->id,
                'delivery_worker_id' => $lockedWorker->id,
                'status' => DeliveryStatus::Assigned->value,
                'reference' => $delivery->reference,
            ], $note);

            return $delivery->load(['worker', 'merchantOrder']);
        }, 3);
    }

    public function accept(Delivery $delivery, User $worker, int $lockVersion): Delivery
    {
        abort_unless($worker->hasRole('delivery-worker') && $worker->hasPermission('deliveries.accept'), 403);

        return DB::transaction(function () use ($delivery, $worker, $lockVersion) {
            $locked = Delivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            if ($locked->delivery_worker_id !== $worker->id) {
                abort(403);
            }
            if ($locked->status === DeliveryStatus::Accepted) {
                return $locked;
            }
            if ($locked->status !== DeliveryStatus::Assigned) {
                throw ValidationException::withMessages(['delivery' => 'المهمة ليست قابلة للقبول حاليًا.']);
            }
            if ($locked->lock_version !== $lockVersion) {
                throw ValidationException::withMessages(['lock_version' => 'تم تحديث المهمة؛ حدّث الصفحة.']);
            }

            $locked->update([
                'status' => DeliveryStatus::Accepted,
                'accepted_at' => now(),
                'lock_version' => $locked->lock_version + 1,
            ]);
            $this->event(
                $locked,
                'accepted',
                DeliveryStatus::Assigned,
                DeliveryStatus::Accepted,
                $worker,
            );
            $this->audit->record('delivery.accepted', $locked, [
                'status' => DeliveryStatus::Assigned->value,
            ], [
                'status' => DeliveryStatus::Accepted->value,
                'delivery_worker_id' => $worker->id,
                'lock_version' => $locked->lock_version,
            ]);
            $this->availability->touch($worker);

            return $locked->refresh();
        }, 3);
    }

    public function unassign(Delivery $delivery, User $actor, string $reason, int $lockVersion): Delivery
    {
        abort_unless($actor->hasPermission('deliveries.manage'), 403);

        return DB::transaction(function () use ($delivery, $actor, $reason, $lockVersion) {
            $locked = Delivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== DeliveryStatus::Assigned || $locked->delivery_worker_id === null) {
                throw ValidationException::withMessages(['delivery' => 'يمكن إلغاء إسناد مهمة بانتظار قبول العامل فقط.']);
            }
            if ($locked->lock_version !== $lockVersion) {
                throw ValidationException::withMessages(['lock_version' => 'تم تحديث المهمة؛ حدّث الصفحة.']);
            }
            $oldWorkerId = $locked->delivery_worker_id;
            $locked->update([
                'delivery_worker_id' => null, 'status' => DeliveryStatus::Unassigned,
                'accepted_at' => null,
                'expected_delivery_at' => null, 'sla_rule_id' => null,
                'lock_version' => $locked->lock_version + 1,
            ]);
            $this->event($locked, 'unassigned', DeliveryStatus::Assigned, DeliveryStatus::Unassigned, $actor, $reason, ['previous_worker_id' => $oldWorkerId]);
            $this->audit->record('delivery.unassigned', $locked, ['delivery_worker_id' => $oldWorkerId, 'status' => DeliveryStatus::Assigned->value], ['delivery_worker_id' => null, 'status' => DeliveryStatus::Unassigned->value], $reason);

            return $locked->refresh();
        }, 3);
    }

    private function validateOrder(Order $order, MerchantOrder $merchantOrder): void
    {
        if ($merchantOrder->order_id !== $order->id
            || $merchantOrder->status !== MerchantOrderStatus::Confirmed
            || $order->status !== OrderStatus::Confirmed
            || $order->payment_status !== OrderPaymentStatus::Paid) {
            throw ValidationException::withMessages([
                'delivery' => 'لا يمكن إسناد التوصيل قبل تأكيد الطلب وقبول دفعه.',
            ]);
        }
        $destination = $order->delivery_address_snapshot ?? [];
        foreach (['recipient_name', 'governorate', 'city', 'address', 'mobile'] as $field) {
            if (trim((string) ($destination[$field] ?? '')) === '') {
                throw ValidationException::withMessages([
                    'delivery' => 'عنوان استلام الطلب غير مكتمل ولا يمكن إسناده.',
                ]);
            }
        }
        if ($merchantOrder->merchant_id === null) {
            throw ValidationException::withMessages([
                'delivery' => 'عنوان استلام منتجات المنصة غير مضبوط بعد؛ لا يمكن إسناد هذا الطلب.',
            ]);
        }
    }

    private function originSnapshot(MerchantOrder $merchantOrder): array
    {
        $merchantOrder->loadMissing(['merchant.location']);
        $merchant = $merchantOrder->merchant;

        return [
            'merchant_id' => $merchant->id,
            'contact_name' => $merchant->legal_name,
            'phone' => $merchant->phone,
            'address' => $merchant->address,
            'location_id' => $merchant->location_id,
            'location_name' => $merchant->location?->name,
            'captured_at' => now()->toIso8601String(),
        ];
    }

    private function event(
        Delivery $delivery,
        string $eventType,
        ?DeliveryStatus $from,
        DeliveryStatus $to,
        User $actor,
        ?string $note = null,
        ?array $metadata = null,
    ): DeliveryEvent {
        return DeliveryEvent::query()->create([
            'delivery_id' => $delivery->id,
            'event_type' => $eventType,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'actor_id' => $actor->id,
            'note' => $note,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}

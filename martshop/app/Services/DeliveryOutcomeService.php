<?php
namespace App\Services;
use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\DeliveryEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class DeliveryOutcomeService
{
    public function __construct(private readonly AuditLogger $audit) {}
    public function record(Delivery $delivery, User $actor, DeliveryStatus $outcome, string $reason, int $version): Delivery
    {
        abort_unless($actor->hasPermission('deliveries.manage'), 403);
        return DB::transaction(function () use ($delivery, $actor, $outcome, $reason, $version) {
            $locked = Delivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            if ($locked->status->isTerminal()) throw ValidationException::withMessages(['delivery' => 'مهمة التوصيل منتهية بالفعل.']);
            if ($locked->lock_version !== $version) throw ValidationException::withMessages(['lock_version' => 'تم تحديث المهمة؛ حدّث الصفحة.']);
            $allowed = match ($outcome) {
                DeliveryStatus::Cancelled => [DeliveryStatus::Assigned, DeliveryStatus::Accepted],
                DeliveryStatus::Failed => [DeliveryStatus::Accepted, DeliveryStatus::PickedUp, DeliveryStatus::InTransit],
                DeliveryStatus::Returned => [DeliveryStatus::PickedUp, DeliveryStatus::InTransit],
                default => [],
            };
            if (! in_array($locked->status, $allowed, true)) throw ValidationException::withMessages(['status' => 'هذه النتيجة غير مسموحة في المرحلة الحالية.']);
            $from = $locked->status;
            $timeField = match ($outcome) { DeliveryStatus::Failed => 'failed_at', DeliveryStatus::Returned => 'returned_at', DeliveryStatus::Cancelled => 'cancelled_at' };
            $locked->update(['status' => $outcome, $timeField => now(), 'outcome_by' => $actor->id, 'outcome_reason' => trim($reason), 'lock_version' => $locked->lock_version + 1]);
            DeliveryEvent::create(['delivery_id' => $locked->id, 'event_type' => $outcome->value, 'from_status' => $from->value, 'to_status' => $outcome->value, 'actor_id' => $actor->id, 'note' => $reason, 'created_at' => now()]);
            $this->audit->record('delivery.'.$outcome->value, $locked, ['status' => $from->value], ['status' => $outcome->value, 'lock_version' => $locked->lock_version], $reason);
            return $locked->refresh();
        }, 3);
    }
}

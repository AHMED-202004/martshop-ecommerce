<?php
namespace App\Services;
use App\Models\Delivery;
use App\Models\DeliveryEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class DeliveryDelayService
{
    public function __construct(private readonly AuditLogger $audit) {}
    public function record(Delivery $delivery, User $actor, string $reason, string $responsibility, ?string $note, int $version): Delivery
    {
        $manager = $actor->hasPermission('deliveries.manage');
        abort_unless($manager || ($delivery->delivery_worker_id === $actor->id && $actor->hasPermission('deliveries.update-status')), 403);
        return DB::transaction(function () use ($delivery, $actor, $reason, $responsibility, $note, $version) {
            $locked = Delivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->hasPermission('deliveries.manage') || ($locked->delivery_worker_id === $actor->id && $actor->hasPermission('deliveries.update-status')), 403);
            if ($locked->lock_version !== $version) throw ValidationException::withMessages(['lock_version' => 'تم تحديث المهمة؛ حدّث الصفحة.']);
            if ($locked->status->isTerminal() && $locked->status->value !== 'delivered') throw ValidationException::withMessages(['delivery' => 'لا يمكن تسجيل تأخير لمهمة منتهية دون تسليم.']);
            $before = ['delay_reason' => $locked->delay_reason, 'delay_responsibility' => $locked->delay_responsibility];
            $locked->update(['delay_reason' => $reason, 'delay_responsibility' => $responsibility, 'delay_note' => $note, 'delay_recorded_by' => $actor->id, 'delay_recorded_at' => now(), 'lock_version' => $locked->lock_version + 1]);
            DeliveryEvent::create(['delivery_id' => $locked->id, 'event_type' => 'delay_recorded', 'from_status' => $locked->status->value, 'to_status' => $locked->status->value, 'actor_id' => $actor->id, 'note' => $note, 'metadata' => ['reason' => $reason, 'responsibility' => $responsibility], 'created_at' => now()]);
            $this->audit->record('delivery.delay_recorded', $locked, $before, ['delay_reason' => $reason, 'delay_responsibility' => $responsibility, 'lock_version' => $locked->lock_version]);
            return $locked->refresh();
        }, 3);
    }
}

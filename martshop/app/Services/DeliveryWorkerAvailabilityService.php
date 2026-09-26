<?php

namespace App\Services;

use App\Enums\DeliveryWorkerAvailability;
use App\Models\DeliveryWorkerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryWorkerAvailabilityService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function update(User $worker, User $actor, DeliveryWorkerAvailability $availability, ?string $reason = null): DeliveryWorkerProfile
    {
        abort_unless($worker->hasRole('delivery-worker'), 404);
        $isSelf = $worker->is($actor);
        abort_unless($isSelf || $actor->hasPermission('deliveries.manage'), 403);
        if ($isSelf && $availability === DeliveryWorkerAvailability::Suspended) {
            throw ValidationException::withMessages(['availability' => 'الإيقاف الإداري لا يحدده عامل التوصيل.']);
        }

        return DB::transaction(function () use ($worker, $actor, $availability, $reason) {
            $profile = DeliveryWorkerProfile::query()->firstOrCreate(
                ['user_id' => $worker->id],
                ['availability' => DeliveryWorkerAvailability::Available, 'availability_changed_at' => now()]
            );
            $profile = DeliveryWorkerProfile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();
            $before = $profile->availability->value;
            $profile->update([
                'availability' => $availability,
                'availability_changed_at' => now(),
                'availability_changed_by' => $actor->id,
                'last_activity_at' => $worker->is($actor) ? now() : $profile->last_activity_at,
            ]);
            $this->audit->record('delivery-worker.availability-updated', $worker,
                ['availability' => $before], ['availability' => $availability->value], $reason);

            return $profile->refresh();
        }, 3);
    }

    public function touch(User $worker): void
    {
        DeliveryWorkerProfile::query()->updateOrCreate(
            ['user_id' => $worker->id],
            ['last_activity_at' => now()]
        );
    }
}

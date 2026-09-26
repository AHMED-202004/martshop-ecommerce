<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Models\Location;
use App\Models\StaffWorkSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffScopeScheduleService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param list<int> $locationIds @param list<array{day_of_week: int, is_working: bool|int|string, starts_at: string|null, ends_at: string|null}> $schedule */
    public function update(
        User $actor,
        int $staffId,
        array $locationIds,
        array $schedule,
        string $timezone,
        string $reason,
    ): User {
        abort_unless($actor->hasPermission('roles.manage'), 403);

        return DB::transaction(function () use ($staffId, $locationIds, $schedule, $timezone, $reason) {
            $employee = User::query()
                ->whereKey($staffId)
                ->where(fn (Builder $staff) => $staff
                    ->whereHas('roles', fn (Builder $roles) => $roles
                        ->whereIn('slug', ['admin', 'delivery-worker'])
                        ->orWhereHas('permissions'))
                    ->orWhereHas('directPermissions'))
                ->lockForUpdate()
                ->firstOrFail();
            if ($employee->account_status === AccountStatus::Terminated) {
                throw ValidationException::withMessages(['staff' => 'لا يمكن تعديل دوام أو نطاق موظف منتهية خدمته.']);
            }

            $locations = collect($locationIds)->map(fn ($id) => (int) $id)->unique()->sort()->values();
            if (Location::query()->where('is_active', true)->whereIn('id', $locations)->count() !== $locations->count()) {
                throw ValidationException::withMessages(['location_ids' => 'اختر مواقع نشطة فقط.']);
            }
            $normalizedSchedule = collect($schedule)
                ->map(function (array $day) use ($timezone) {
                    $isWorking = filter_var($day['is_working'], FILTER_VALIDATE_BOOL);
                    $startsAt = $isWorking ? ($day['starts_at'] ?? null) : null;
                    $endsAt = $isWorking ? ($day['ends_at'] ?? null) : null;
                    if ($isWorking && (! $startsAt || ! $endsAt || $endsAt <= $startsAt)) {
                        throw ValidationException::withMessages([
                            'schedule' => 'كل يوم عمل يحتاج وقت بداية ونهاية، ويجب أن تكون النهاية بعد البداية.',
                        ]);
                    }

                    return [
                        'day_of_week' => (int) $day['day_of_week'],
                        'is_working' => $isWorking,
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                        'timezone' => $timezone,
                    ];
                })
                ->sortBy('day_of_week')
                ->values();
            if ($normalizedSchedule->pluck('day_of_week')->all() !== range(0, 6)) {
                throw ValidationException::withMessages(['schedule' => 'يجب إرسال أيام الأسبوع السبعة مرة واحدة.']);
            }

            $before = [
                'location_ids' => $employee->staffLocations()->orderBy('locations.id')->pluck('locations.id')->all(),
                'schedule' => $employee->staffWorkSchedules()->orderBy('day_of_week')->get()
                    ->map(fn (StaffWorkSchedule $day) => $this->scheduleState($day))->all(),
            ];
            $employee->staffLocations()->sync($locations->all());
            foreach ($normalizedSchedule as $day) {
                $employee->staffWorkSchedules()->updateOrCreate(
                    ['day_of_week' => $day['day_of_week']],
                    $day,
                );
            }
            $after = [
                'location_ids' => $locations->all(),
                'schedule' => $employee->staffWorkSchedules()->orderBy('day_of_week')->get()
                    ->map(fn (StaffWorkSchedule $day) => $this->scheduleState($day))->all(),
            ];
            $this->audit->record(
                'staff.scope_schedule_updated',
                $employee,
                before: $before,
                after: $after,
                reason: trim($reason),
            );

            return $employee;
        });
    }

    /** @return array{day_of_week: int, is_working: bool, starts_at: string|null, ends_at: string|null, timezone: string} */
    private function scheduleState(StaffWorkSchedule $day): array
    {
        return [
            'day_of_week' => $day->day_of_week,
            'is_working' => $day->is_working,
            'starts_at' => $day->starts_at,
            'ends_at' => $day->ends_at,
            'timezone' => $day->timezone,
        ];
    }
}

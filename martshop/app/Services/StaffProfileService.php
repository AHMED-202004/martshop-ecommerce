<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Models\StaffDepartment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffProfileService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function update(
        User $actor,
        int $staffId,
        string $name,
        ?string $phone,
        ?string $jobTitle,
        ?int $departmentId,
        string $reason,
    ): User {
        abort_unless($actor->hasPermission('roles.manage'), 403);

        return DB::transaction(function () use ($staffId, $name, $phone, $jobTitle, $departmentId, $reason) {
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
                throw ValidationException::withMessages([
                    'staff' => 'لا يمكن تعديل بيانات موظف منتهية خدمته.',
                ]);
            }
            if ($departmentId !== null && ! StaffDepartment::query()
                ->whereKey($departmentId)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages([
                    'staff_department_id' => 'اختر قسمًا نشطًا.',
                ]);
            }

            $before = $this->auditState($employee);
            $employee->forceFill([
                'name' => trim($name),
                'phone' => $phone ? trim($phone) : null,
                'job_title' => $jobTitle ? trim($jobTitle) : null,
                'staff_department_id' => $departmentId,
            ])->save();
            $after = $this->auditState($employee);
            if ($before !== $after) {
                $this->audit->record(
                    'staff.profile_updated',
                    $employee,
                    before: $before,
                    after: $after,
                    reason: trim($reason),
                );
            }

            return $employee;
        });
    }

    /** @return array{name: string, phone: string|null, job_title: string|null, staff_department_id: int|null} */
    private function auditState(User $employee): array
    {
        return [
            'name' => $employee->name,
            'phone' => $employee->phone,
            'job_title' => $employee->job_title,
            'staff_department_id' => $employee->staff_department_id,
        ];
    }
}

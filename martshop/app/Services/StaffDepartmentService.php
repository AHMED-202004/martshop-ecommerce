<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Models\StaffDepartment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffDepartmentService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function create(
        User $actor,
        string $name,
        string $slug,
        ?int $managerId,
        string $reason,
    ): StaffDepartment {
        abort_unless($actor->hasPermission('roles.manage'), 403);

        return DB::transaction(function () use ($name, $slug, $managerId, $reason) {
            $manager = $this->resolveManager($managerId);
            $department = StaffDepartment::create([
                'name' => trim($name),
                'slug' => strtolower(trim($slug)),
                'is_active' => true,
                'manager_id' => $manager?->getKey(),
            ]);
            $this->audit->record(
                'staff.department_created',
                $department,
                after: $this->auditState($department),
                reason: trim($reason),
            );

            return $department;
        });
    }

    public function update(
        User $actor,
        int $departmentId,
        string $name,
        string $slug,
        ?int $managerId,
        bool $isActive,
        string $reason,
    ): StaffDepartment {
        abort_unless($actor->hasPermission('roles.manage'), 403);

        return DB::transaction(function () use ($departmentId, $name, $slug, $managerId, $isActive, $reason) {
            $department = StaffDepartment::query()->lockForUpdate()->findOrFail($departmentId);
            $before = $this->auditState($department);
            $manager = $this->resolveManager($managerId);
            $department->update([
                'name' => trim($name),
                'slug' => strtolower(trim($slug)),
                'is_active' => $isActive,
                'manager_id' => $manager?->getKey(),
            ]);
            $this->audit->record(
                'staff.department_updated',
                $department,
                before: $before,
                after: $this->auditState($department),
                reason: trim($reason),
            );

            return $department;
        });
    }

    private function resolveManager(?int $managerId): ?User
    {
        if ($managerId === null) {
            return null;
        }

        $manager = User::query()
            ->whereKey($managerId)
            ->where('account_status', AccountStatus::Active->value)
            ->where(fn (Builder $staff) => $staff
                ->whereHas('roles', fn (Builder $roles) => $roles
                    ->whereIn('slug', ['admin', 'delivery-worker'])
                    ->orWhereHas('permissions'))
                ->orWhereHas('directPermissions'))
            ->first();

        if (! $manager) {
            throw ValidationException::withMessages([
                'manager_id' => 'مدير القسم يجب أن يكون موظفًا تشغيليًا نشطًا.',
            ]);
        }

        return $manager;
    }

    /** @return array{name: string, slug: string, is_active: bool, manager_id: int|null} */
    private function auditState(StaffDepartment $department): array
    {
        return [
            'name' => $department->name,
            'slug' => $department->slug,
            'is_active' => $department->is_active,
            'manager_id' => $department->manager_id,
        ];
    }
}

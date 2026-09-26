<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BulkStaffRoleService
{
    public function __construct(private readonly StaffSecurityService $security) {}

    public function update(User $actor, array $staffIds, array $roleIds, string $reason): int
    {
        abort_unless($actor->hasPermission('roles.manage'), 403);
        $staffIds = collect($staffIds)->map(fn ($id) => (int) $id)->unique()->values();
        $roleIds = collect($roleIds)->map(fn ($id) => (int) $id)->unique()->values();
        if ($staffIds->contains($actor->id)) {
            throw ValidationException::withMessages(['staff_ids' => 'لا يمكن تضمين حسابك في التعديل الجماعي.']);
        }

        $roles = Role::query()->with('permissions:id,slug')->whereIn('id', $roleIds)->get();
        if ($roles->count() !== $roleIds->count()) {
            throw ValidationException::withMessages(['role_ids' => 'تحتوي القائمة على دور غير صالح.']);
        }
        $sensitive = $roles->contains(fn (Role $role) => $role->slug === 'admin'
            || $role->permissions->contains(fn ($permission) => preg_match('/^(roles|payments|withdrawals|settings|audit)\./', $permission->slug)));
        if ($sensitive) {
            throw ValidationException::withMessages(['role_ids' => 'الأدوار الإدارية أو المالية أو الأمنية الحساسة تُعدّل فرديًا فقط.']);
        }

        return DB::transaction(function () use ($actor, $staffIds, $roleIds, $reason) {
            if (User::query()->whereIn('id', $staffIds)->lockForUpdate()->count() !== $staffIds->count()) {
                throw ValidationException::withMessages(['staff_ids' => 'تعذر العثور على كل الموظفين المحددين.']);
            }
            $revoked = 0;
            foreach ($staffIds as $staffId) {
                $revoked += $this->security->updateOperationalRoles($actor, $staffId, $roleIds->all(), 'تعديل جماعي: '.trim($reason));
            }
            return $revoked;
        }, 3);
    }
}

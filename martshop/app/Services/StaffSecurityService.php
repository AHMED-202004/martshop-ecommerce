<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\DeliveryStatus;
use App\Enums\StaffTaskStatus;
use App\Enums\SupportTicketStatus;
use App\Models\ContactMessage;
use App\Models\Delivery;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StaffTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StaffSecurityService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function revokeSessions(User $actor, int $staffId, string $reason): int
    {
        abort_unless($actor->hasPermission('roles.manage'), 403);

        return DB::transaction(function () use ($actor, $staffId, $reason) {
            if ($actor->getKey() === $staffId) {
                throw ValidationException::withMessages([
                    'staff' => 'لا يمكنك إنهاء جلستك الحالية من إجراء موظف آخر.',
                ]);
            }

            $employee = User::query()
                ->whereKey($staffId)
                ->whereHas('roles', fn (Builder $roles) => $roles
                    ->whereIn('slug', ['admin', 'delivery-worker'])
                    ->orWhereHas('permissions'))
                ->lockForUpdate()
                ->firstOrFail();

            $revoked = 0;
            if (config('session.driver') === 'database') {
                $revoked = DB::connection(config('session.connection'))
                    ->table(config('session.table', 'sessions'))
                    ->where('user_id', $employee->id)
                    ->delete();
            }

            $employee->forceFill(['remember_token' => Str::random(60)])->save();
            $this->audit->record(
                'staff.sessions_revoked',
                $employee,
                before: ['stored_sessions' => $revoked],
                after: ['stored_sessions' => 0, 'remember_token_rotated' => true],
                reason: trim($reason),
            );

            return $revoked;
        });
    }

    public function changeStatus(
        User $actor,
        int $staffId,
        AccountStatus $status,
        string $reason,
    ): int {
        abort_unless($actor->hasPermission('roles.manage'), 403);

        if (! in_array($status, [AccountStatus::Active, AccountStatus::OnLeave, AccountStatus::Suspended], true)) {
            throw ValidationException::withMessages([
                'status' => 'تغيير هذه الحالة يحتاج إلى مسار إداري مستقل.',
            ]);
        }

        return DB::transaction(function () use ($actor, $staffId, $status, $reason) {
            if ($actor->getKey() === $staffId) {
                throw ValidationException::withMessages([
                    'staff' => 'لا يمكنك تغيير حالة حسابك الحالي.',
                ]);
            }

            $employee = $this->findOperationalStaff($staffId);
            $previousStatus = $employee->account_status;

            if ($previousStatus === $status) {
                throw ValidationException::withMessages([
                    'status' => 'الحساب موجود في هذه الحالة بالفعل.',
                ]);
            }

            if (! in_array($previousStatus, [AccountStatus::Active, AccountStatus::OnLeave, AccountStatus::Suspended], true)) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن تغيير هذه الحالة من شاشة التعليق.',
                ]);
            }

            if ($status !== AccountStatus::Active) {
                $adminRoleId = Role::query()->where('slug', 'admin')->value('id');
                if ($adminRoleId && $employee->roles()->whereKey($adminRoleId)->exists()) {
                    $this->ensureAnotherActiveAdminExists($employee, (int) $adminRoleId, 'status');
                }
            }

            $revoked = 0;
            $tokenRotated = false;
            if ($status !== AccountStatus::Active) {
                $revoked = $this->deleteStoredSessions($employee);
                $employee->remember_token = Str::random(60);
                $tokenRotated = true;
            }

            $employee->account_status = $status;
            $employee->account_status_changed_at = now();
            $employee->account_status_changed_by = $actor->getKey();
            $employee->save();

            $this->audit->record(
                'staff.account_status_changed',
                $employee,
                before: ['account_status' => $previousStatus->value],
                after: [
                    'account_status' => $status->value,
                    'stored_sessions_revoked' => $revoked,
                    'remember_token_rotated' => $tokenRotated,
                ],
                reason: trim($reason),
            );

            return $revoked;
        });
    }

    /**
     * @param  list<int>  $roleIds
     */
    public function updateOperationalRoles(User $actor, int $staffId, array $roleIds, string $reason): int
    {
        abort_unless($actor->hasPermission('roles.manage'), 403);

        return DB::transaction(function () use ($actor, $staffId, $roleIds, $reason) {
            if ($actor->getKey() === $staffId) {
                throw ValidationException::withMessages([
                    'staff' => 'لا يمكنك تعديل أدوار حسابك الحالي.',
                ]);
            }

            $employee = $this->findOperationalStaff($staffId);
            if ($employee->account_status === AccountStatus::Terminated) {
                throw ValidationException::withMessages([
                    'staff' => 'لا يمكن تعديل أدوار موظف منتهية خدمته.',
                ]);
            }
            $manageableRoles = Role::query()
                ->where(fn (Builder $roles) => $roles
                    ->whereIn('slug', ['admin', 'delivery-worker'])
                    ->orWhereHas('permissions'))
                ->lockForUpdate()
                ->get(['id', 'slug']);
            $requested = collect($roleIds)->map(fn ($id) => (int) $id)->unique()->sort()->values();
            $allowedIds = $manageableRoles->pluck('id')->map(fn ($id) => (int) $id);

            if ($requested->isEmpty() || $requested->diff($allowedIds)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'role_ids' => 'اختر أدوارًا تشغيلية صالحة فقط.',
                ]);
            }

            $employee->load('roles:id,slug');
            $currentOperational = $employee->roles->whereIn('id', $allowedIds)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
            if ($currentOperational->all() === $requested->all()) {
                throw ValidationException::withMessages([
                    'role_ids' => 'لم تتغير الأدوار التشغيلية.',
                ]);
            }

            $adminRole = $manageableRoles->firstWhere('slug', 'admin');
            if ($adminRole && $currentOperational->contains($adminRole->id) && ! $requested->contains($adminRole->id)) {
                $this->ensureAnotherActiveAdminExists($employee, (int) $adminRole->id);
            }

            $preservedRoleIds = $employee->roles->pluck('id')->map(fn ($id) => (int) $id)->diff($allowedIds);
            $before = $employee->roles->whereIn('id', $allowedIds)->pluck('slug')->sort()->values()->all();
            $after = $manageableRoles->whereIn('id', $requested)->pluck('slug')->sort()->values()->all();
            $employee->roles()->sync($preservedRoleIds->merge($requested)->unique()->all());
            $revoked = $this->deleteStoredSessions($employee);
            $employee->forceFill(['remember_token' => Str::random(60)])->save();

            $this->audit->record(
                'staff.operational_roles_changed',
                $employee,
                before: ['roles' => $before],
                after: [
                    'roles' => $after,
                    'stored_sessions_revoked' => $revoked,
                    'remember_token_rotated' => true,
                ],
                reason: trim($reason),
            );

            return $revoked;
        });
    }

    /**
     * @param  list<int>  $permissionIds
     */
    public function updateDirectPermissions(User $actor, int $staffId, array $permissionIds, string $reason): int
    {
        abort_unless($actor->hasPermission('roles.manage'), 403);

        return DB::transaction(function () use ($actor, $staffId, $permissionIds, $reason) {
            if ($actor->getKey() === $staffId) {
                throw ValidationException::withMessages([
                    'staff' => 'لا يمكنك تعديل صلاحيات حسابك الحالي.',
                ]);
            }

            $employee = $this->findOperationalStaff($staffId);
            if ($employee->account_status === AccountStatus::Terminated) {
                throw ValidationException::withMessages([
                    'staff' => 'لا يمكن تعديل صلاحيات موظف منتهية خدمته.',
                ]);
            }
            $requested = collect($permissionIds)->map(fn ($id) => (int) $id)->unique()->sort()->values();
            $permissions = Permission::query()->whereIn('id', $requested)->lockForUpdate()->get(['id', 'slug']);
            if ($permissions->count() !== $requested->count()) {
                throw ValidationException::withMessages([
                    'permission_ids' => 'تحتوي القائمة على صلاحية غير صالحة.',
                ]);
            }

            $employee->load('directPermissions:id,slug');
            $current = $employee->directPermissions->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
            if ($current->all() === $requested->all()) {
                throw ValidationException::withMessages([
                    'permission_ids' => 'لم تتغير الصلاحيات المباشرة.',
                ]);
            }

            $before = $employee->directPermissions->pluck('slug')->sort()->values()->all();
            $after = $permissions->pluck('slug')->sort()->values()->all();
            $employee->directPermissions()->sync($requested->all());
            $revoked = $this->deleteStoredSessions($employee);
            $employee->forceFill(['remember_token' => Str::random(60)])->save();

            $this->audit->record(
                'staff.direct_permissions_changed',
                $employee,
                before: ['permissions' => $before],
                after: [
                    'permissions' => $after,
                    'stored_sessions_revoked' => $revoked,
                    'remember_token_rotated' => true,
                ],
                reason: trim($reason),
            );

            return $revoked;
        });
    }

    public function terminate(
        User $actor,
        int $staffId,
        string $confirmation,
        string $reason,
        ?int $taskReplacementId = null,
    ): int {
        abort_unless($actor->hasPermission('roles.manage'), 403);

        return DB::transaction(function () use ($actor, $staffId, $confirmation, $reason, $taskReplacementId) {
            if ($actor->getKey() === $staffId) {
                throw ValidationException::withMessages([
                    'staff' => 'لا يمكنك إنهاء خدمة حسابك الحالي.',
                ]);
            }

            $employee = $this->findOperationalStaff($staffId);
            if ($employee->account_status === AccountStatus::Terminated) {
                throw ValidationException::withMessages([
                    'staff' => 'خدمة هذا الحساب منتهية بالفعل.',
                ]);
            }
            if (! hash_equals(strtolower($employee->email), strtolower(trim($confirmation)))) {
                throw ValidationException::withMessages([
                    'confirmation' => 'بريد التأكيد لا يطابق حساب الموظف.',
                ]);
            }

            $activeDeliveries = Delivery::query()
                ->where('delivery_worker_id', $employee->id)
                ->whereNotIn('status', collect(DeliveryStatus::cases())->filter->isTerminal()->map->value)
                ->lockForUpdate()
                ->count();
            if ($activeDeliveries > 0) {
                throw ValidationException::withMessages([
                    'staff' => 'يجب إعادة إسناد عمليات التوصيل النشطة قبل إنهاء الخدمة.',
                ]);
            }
            $activeChats = ContactMessage::query()
                ->where('assigned_to', $employee->id)
                ->whereNotIn('status', [SupportTicketStatus::Resolved->value, SupportTicketStatus::Closed->value])
                ->lockForUpdate()->count();
            if ($activeChats > 0) {
                throw ValidationException::withMessages(['staff' => 'يجب إعادة إسناد المحادثات والتذاكر النشطة قبل إنهاء الخدمة.']);
            }
            $activeTasks = StaffTask::query()
                ->where('assigned_to', $employee->id)
                ->whereNotIn('status', [StaffTaskStatus::Completed->value, StaffTaskStatus::Cancelled->value])
                ->lockForUpdate()
                ->get(['id', 'assigned_to', 'status', 'version']);
            if ($activeTasks->isNotEmpty() && $taskReplacementId === null) {
                throw ValidationException::withMessages([
                    'reassign_tasks_to' => 'اختر موظفًا بديلًا للمهام المفتوحة قبل إنهاء الخدمة.',
                ]);
            }
            $replacement = $taskReplacementId === null ? null : User::query()
                ->whereKey($taskReplacementId)
                ->where('account_status', AccountStatus::Active->value)
                ->where(fn (Builder $staff) => $staff
                    ->whereHas('roles', fn (Builder $roles) => $roles
                        ->whereIn('slug', ['admin', 'delivery-worker'])
                        ->orWhereHas('permissions'))
                    ->orWhereHas('directPermissions'))
                ->lockForUpdate()
                ->first();
            if ($taskReplacementId !== null && ! $replacement) {
                throw ValidationException::withMessages([
                    'reassign_tasks_to' => 'الموظف البديل يجب أن يكون تشغيليًا ونشطًا.',
                ]);
            }
            foreach ($activeTasks as $task) {
                $before = [
                    'assigned_to' => $task->assigned_to,
                    'status' => $task->status->value,
                    'version' => $task->version,
                ];
                $task->forceFill([
                    'assigned_to' => $replacement->getKey(),
                    'status' => StaffTaskStatus::Assigned,
                    'version' => $task->version + 1,
                ])->save();
                $this->audit->record(
                    'staff.task_reassigned_on_termination',
                    $task,
                    before: $before,
                    after: [
                        'assigned_to' => $replacement->getKey(),
                        'status' => StaffTaskStatus::Assigned->value,
                        'version' => $task->version,
                    ],
                    reason: trim($reason),
                );
            }

            $adminRoleId = Role::query()->where('slug', 'admin')->value('id');
            if ($employee->account_status === AccountStatus::Active
                && $adminRoleId
                && $employee->roles()->whereKey($adminRoleId)->exists()) {
                $this->ensureAnotherActiveAdminExists($employee, (int) $adminRoleId, 'staff');
            }

            $previousStatus = $employee->account_status;
            $revoked = $this->deleteStoredSessions($employee);
            DB::table(config('auth.passwords.users.table', 'password_reset_tokens'))
                ->where('email', $employee->email)
                ->delete();
            $employee->forceFill([
                'account_status' => AccountStatus::Terminated,
                'account_status_changed_at' => now(),
                'account_status_changed_by' => $actor->getKey(),
                'remember_token' => Str::random(60),
            ])->save();

            $this->audit->record(
                'staff.terminated',
                $employee,
                before: ['account_status' => $previousStatus->value],
                after: [
                    'account_status' => AccountStatus::Terminated->value,
                    'active_delivery_assignments' => 0,
                    'active_staff_tasks' => 0,
                    'staff_tasks_reassigned' => $activeTasks->count(),
                    'task_replacement_id' => $replacement?->getKey(),
                    'stored_sessions_revoked' => $revoked,
                    'remember_token_rotated' => true,
                ],
                reason: trim($reason),
            );

            return $revoked;
        });
    }

    public function updateEmail(User $actor, int $staffId, string $email, string $reason): int
    {
        abort_unless($actor->hasPermission('roles.manage'), 403);

        return DB::transaction(function () use ($actor, $staffId, $email, $reason) {
            if ($actor->getKey() === $staffId) {
                throw ValidationException::withMessages([
                    'staff' => 'لا يمكنك تغيير بريد حسابك من إجراء موظف آخر.',
                ]);
            }
            $employee = $this->findOperationalStaff($staffId);
            if ($employee->account_status === AccountStatus::Terminated) {
                throw ValidationException::withMessages([
                    'staff' => 'لا يمكن تغيير بريد موظف منتهية خدمته.',
                ]);
            }
            $newEmail = strtolower(trim($email));
            $oldEmail = strtolower($employee->email);
            if (hash_equals($oldEmail, $newEmail)) {
                return 0;
            }

            DB::table(config('auth.passwords.users.table', 'password_reset_tokens'))
                ->whereIn('email', [$oldEmail, $newEmail])
                ->delete();
            $revoked = $this->deleteStoredSessions($employee);
            $employee->forceFill([
                'email' => $newEmail,
                'email_verified_at' => null,
                'remember_token' => Str::random(60),
            ])->save();
            $this->audit->record(
                'staff.email_changed',
                $employee,
                before: ['email' => $oldEmail],
                after: [
                    'email' => $newEmail,
                    'email_verified_at' => null,
                    'stored_sessions_revoked' => $revoked,
                    'password_tokens_revoked' => true,
                    'remember_token_rotated' => true,
                ],
                reason: trim($reason),
            );

            return $revoked;
        });
    }

    private function findOperationalStaff(int $staffId): User
    {
        return User::query()
            ->whereKey($staffId)
            ->where(fn (Builder $staff) => $staff
                ->whereHas('roles', fn (Builder $roles) => $roles
                    ->whereIn('slug', ['admin', 'delivery-worker'])
                    ->orWhereHas('permissions'))
                ->orWhereHas('directPermissions'))
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function deleteStoredSessions(User $employee): int
    {
        if (config('session.driver') !== 'database') {
            return 0;
        }

        return DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $employee->id)
            ->delete();
    }

    private function ensureAnotherActiveAdminExists(User $employee, int $adminRoleId, string $errorField = 'role_ids'): void
    {
        $anotherActiveAdminExists = User::query()
            ->whereKeyNot($employee->id)
            ->where('account_status', AccountStatus::Active->value)
            ->whereHas('roles', fn (Builder $roles) => $roles->whereKey($adminRoleId))
            ->lockForUpdate()
            ->exists();

        if (! $anotherActiveAdminExists) {
            throw ValidationException::withMessages([
                $errorField => 'لا يمكن تعطيل آخر أدمن نشط.',
            ]);
        }
    }
}

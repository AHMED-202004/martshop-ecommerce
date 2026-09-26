<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\StaffInvitation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class StaffInvitationService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PasswordRecoveryService $passwordRecovery,
        private readonly StaffIdentityService $identity,
    ) {}

    /**
     * @param  list<int>  $roleIds
     * @param  list<int>  $permissionIds
     * @return array{user: User, delivered: bool, delivery_error: class-string<Throwable>|null}
     */
    public function invite(
        User $actor,
        string $name,
        string $email,
        ?string $jobTitle,
        ?int $departmentId,
        array $roleIds,
        array $permissionIds,
        string $reason,
    ): array {
        abort_unless($actor->hasPermission('roles.manage'), 403);
        if (! $this->passwordRecovery->mailReady()) {
            throw ValidationException::withMessages([
                'email' => 'إرسال بريد الدعوات غير مفعّل حاليًا.',
            ]);
        }

        [$roles, $permissions] = $this->validatedAssignments($roleIds, $permissionIds);
        try {
            $employee = DB::transaction(function () use ($actor, $name, $email, $jobTitle, $departmentId, $roles, $permissions, $reason) {
                $employee = User::forceCreate([
                    'name' => trim($name),
                    'email' => strtolower(trim($email)),
                    'password' => Hash::make(Str::random(64)),
                    'remember_token' => Str::random(60),
                    'account_status' => AccountStatus::Invited,
                    'account_status_changed_at' => now(),
                    'account_status_changed_by' => $actor->getKey(),
                    'job_title' => $jobTitle ? trim($jobTitle) : null,
                    'staff_department_id' => $departmentId,
                ]);
                $this->identity->ensureEmployeeNumber($employee);
                $employee->roles()->sync($roles->pluck('id')->all());
                $employee->directPermissions()->sync($permissions->pluck('id')->all());
                $this->audit->record(
                    'staff.invited',
                    $employee,
                    after: [
                        'account_status' => AccountStatus::Invited->value,
                        'roles' => $roles->pluck('slug')->sort()->values()->all(),
                        'direct_permissions' => $permissions->pluck('slug')->sort()->values()->all(),
                        'employee_number' => $employee->employee_number,
                        'job_title' => $employee->job_title,
                        'staff_department_id' => $employee->staff_department_id,
                    ],
                    reason: trim($reason),
                );

                return $employee;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'البريد مستخدم في حساب موجود.']);
        }

        try {
            $token = Password::broker('users')->createToken($employee);
            $employee->notify(new StaffInvitation($token));

            return ['user' => $employee, 'delivered' => true, 'delivery_error' => null];
        } catch (Throwable $exception) {
            DB::table(config('auth.passwords.users.table', 'password_reset_tokens'))
                ->where('email', $employee->email)->delete();
            Log::warning('Staff invitation delivery failed.', ['exception_type' => $exception::class]);
            $this->audit->record(
                'staff.invitation_delivery_failed',
                $employee,
                reason: 'Invitation delivery failed; no usable invitation token remains.',
            );

            return ['user' => $employee, 'delivered' => false, 'delivery_error' => $exception::class];
        }
    }

    public function resend(User $actor, int $staffId, string $reason): bool
    {
        abort_unless($actor->hasPermission('roles.manage'), 403);
        if (! $this->passwordRecovery->mailReady()) {
            throw ValidationException::withMessages([
                'email' => 'إرسال بريد الدعوات غير مفعّل حاليًا.',
            ]);
        }

        $employee = User::query()
            ->whereKey($staffId)
            ->where('account_status', AccountStatus::Invited->value)
            ->where(fn (Builder $staff) => $staff
                ->whereHas('roles', fn (Builder $roles) => $roles
                    ->whereIn('slug', ['admin', 'delivery-worker'])
                    ->orWhereHas('permissions'))
                ->orWhereHas('directPermissions'))
            ->firstOrFail();

        try {
            $token = Password::broker('users')->createToken($employee);
            $employee->notify(new StaffInvitation($token));
            $this->audit->record(
                'staff.invitation_resent',
                $employee,
                reason: trim($reason),
            );

            return true;
        } catch (Throwable $exception) {
            DB::table(config('auth.passwords.users.table', 'password_reset_tokens'))
                ->where('email', $employee->email)->delete();
            Log::warning('Staff invitation redelivery failed.', ['exception_type' => $exception::class]);
            $this->audit->record(
                'staff.invitation_delivery_failed',
                $employee,
                reason: 'Invitation redelivery failed; no usable invitation token remains.',
            );

            return false;
        }
    }

    /**
     * @param  list<int>  $roleIds
     * @param  list<int>  $permissionIds
     */
    private function validatedAssignments(array $roleIds, array $permissionIds): array
    {
        $requestedRoles = collect($roleIds)->map(fn ($id) => (int) $id)->unique()->sort()->values();
        $roles = Role::query()
            ->whereIn('id', $requestedRoles)
            ->where(fn (Builder $query) => $query
                ->whereIn('slug', ['admin', 'delivery-worker'])
                ->orWhereHas('permissions'))
            ->get(['id', 'slug']);
        if ($requestedRoles->isEmpty() || $roles->count() !== $requestedRoles->count()) {
            throw ValidationException::withMessages(['role_ids' => 'اختر أدوارًا تشغيلية صالحة فقط.']);
        }

        $requestedPermissions = collect($permissionIds)->map(fn ($id) => (int) $id)->unique()->sort()->values();
        $permissions = Permission::query()->whereIn('id', $requestedPermissions)->get(['id', 'slug']);
        if ($permissions->count() !== $requestedPermissions->count()) {
            throw ValidationException::withMessages(['permission_ids' => 'تحتوي القائمة على صلاحية غير صالحة.']);
        }

        return [$roles, $permissions];
    }
}

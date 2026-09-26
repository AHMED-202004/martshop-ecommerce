<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryWorkerService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly StaffIdentityService $identity,
    ) {}

    public function promoteByEmail(string $email, User $actor): User
    {
        abort_unless($actor->hasPermission('deliveries.manage'), 403);

        return DB::transaction(function () use ($email, $actor) {
            $worker = User::query()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])
                ->lockForUpdate()
                ->firstOrFail();
            if ($worker->hasRole('admin') || $worker->merchant()->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'يجب استخدام حساب موظف مستقل، وليس حساب إدارة أو تاجر.',
                ]);
            }
            if ($worker->hasRole('delivery-worker')) {
                return $worker;
            }

            $role = Role::query()->where('slug', 'delivery-worker')->firstOrFail();
            $worker->assignRole($role);
            $this->identity->ensureEmployeeNumber($worker);
            $this->audit->record('delivery_worker.role_assigned', $worker, null, [
                'user_id' => $worker->id,
                'email' => $worker->email,
                'role' => $role->slug,
            ], metadata: ['actor_id' => $actor->id]);

            return $worker->refresh();
        }, 3);
    }
}

<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\StaffTaskPriority;
use App\Enums\StaffTaskStatus;
use App\Models\ContactMessage;
use App\Models\Delivery;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StaffTask;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffTaskService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function create(
        User $actor,
        int $assigneeId,
        string $title,
        ?string $description,
        string $taskType,
        StaffTaskPriority $priority,
        ?string $dueAt,
        ?string $notes,
        ?int $relatedId,
        string $reason,
    ): StaffTask {
        abort_unless($actor->hasPermission('roles.manage'), 403);

        return DB::transaction(function () use ($actor, $assigneeId, $title, $description, $taskType, $priority, $dueAt, $notes, $relatedId, $reason) {
            $assignee = $this->activeOperationalStaff($assigneeId);
            [$relatedType, $resolvedRelatedId] = $this->resolveRelated($taskType, $relatedId);
            $task = StaffTask::create([
                'title' => trim($title),
                'description' => $description ? trim($description) : null,
                'task_type' => $taskType,
                'priority' => $priority,
                'assigned_to' => $assignee->getKey(),
                'assigned_by' => $actor->getKey(),
                'related_type' => $relatedType,
                'related_id' => $resolvedRelatedId,
                'due_at' => $dueAt ? Carbon::parse($dueAt) : null,
                'status' => StaffTaskStatus::Assigned,
                'notes' => $notes ? trim($notes) : null,
            ]);
            $this->audit->record(
                'staff.task_created',
                $task,
                after: $this->auditState($task),
                reason: trim($reason),
            );

            return $task;
        });
    }

    public function update(
        User $actor,
        int $taskId,
        int $assigneeId,
        StaffTaskPriority $priority,
        ?string $dueAt,
        StaffTaskStatus $status,
        int $expectedVersion,
        string $reason,
    ): StaffTask {
        abort_unless($actor->hasPermission('roles.manage'), 403);

        return DB::transaction(function () use ($taskId, $assigneeId, $priority, $dueAt, $status, $expectedVersion, $reason) {
            $task = StaffTask::query()->lockForUpdate()->findOrFail($taskId);
            if ($task->version !== $expectedVersion) {
                throw ValidationException::withMessages([
                    'task' => 'تغيرت المهمة منذ فتح الصفحة. حدّث الصفحة وراجع آخر حالة.',
                ]);
            }
            if (in_array($task->status, [StaffTaskStatus::Completed, StaffTaskStatus::Cancelled], true)) {
                throw ValidationException::withMessages(['task' => 'المهمة المغلقة لا يمكن تعديلها.']);
            }
            $assignee = $this->activeOperationalStaff($assigneeId);
            if ($task->assigned_to !== $assignee->getKey() && $status !== StaffTaskStatus::Assigned) {
                throw ValidationException::withMessages([
                    'status' => 'إعادة الإسناد تتطلب إعادة الحالة إلى مسندة.',
                ]);
            }
            $this->assertTransition($task->status, $status);

            $before = $this->auditState($task);
            $task->forceFill([
                'assigned_to' => $assignee->getKey(),
                'priority' => $priority,
                'due_at' => $dueAt ? Carbon::parse($dueAt) : null,
                'status' => $status,
                'started_at' => $status === StaffTaskStatus::InProgress
                    ? ($task->started_at ?? now()) : $task->started_at,
                'completed_at' => $status === StaffTaskStatus::Completed ? now() : null,
                'version' => $task->version + 1,
            ])->save();
            $this->audit->record(
                'staff.task_updated',
                $task,
                before: $before,
                after: $this->auditState($task),
                reason: trim($reason),
            );

            return $task;
        });
    }

    public function updateOwnStatus(
        User $actor,
        int $taskId,
        StaffTaskStatus $status,
        int $expectedVersion,
        string $progressNote,
    ): StaffTask {
        abort_unless($actor->hasPermission('staff-tasks.update-own'), 403);

        return DB::transaction(function () use ($actor, $taskId, $status, $expectedVersion, $progressNote) {
            $task = StaffTask::query()
                ->whereKey($taskId)
                ->where('assigned_to', $actor->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            if ($task->version !== $expectedVersion) {
                throw ValidationException::withMessages([
                    'task' => 'تغيرت المهمة منذ فتح الصفحة. حدّث الصفحة وراجع آخر حالة.',
                ]);
            }
            if (in_array($task->status, [StaffTaskStatus::Completed, StaffTaskStatus::Cancelled], true)) {
                throw ValidationException::withMessages(['task' => 'المهمة المغلقة لا يمكن تعديلها.']);
            }
            $this->assertTransition($task->status, $status);

            $before = $this->auditState($task);
            $task->forceFill([
                'status' => $status,
                'started_at' => $status === StaffTaskStatus::InProgress
                    ? ($task->started_at ?? now()) : $task->started_at,
                'completed_at' => $status === StaffTaskStatus::Completed ? now() : null,
                'version' => $task->version + 1,
            ])->save();
            $this->audit->record(
                'staff.task_status_updated',
                $task,
                before: $before,
                after: $this->auditState($task),
                reason: trim($progressNote),
            );

            return $task;
        });
    }

    private function activeOperationalStaff(int $staffId): User
    {
        $employee = User::query()
            ->whereKey($staffId)
            ->where('account_status', AccountStatus::Active->value)
            ->where(fn (Builder $staff) => $staff
                ->whereHas('roles', fn (Builder $roles) => $roles
                    ->whereIn('slug', ['admin', 'delivery-worker'])
                    ->orWhereHas('permissions'))
                ->orWhereHas('directPermissions'))
            ->lockForUpdate()
            ->first();

        if (! $employee) {
            throw ValidationException::withMessages([
                'assigned_to' => 'لا يمكن إسناد المهمة إلا لموظف تشغيلي نشط.',
            ]);
        }

        return $employee;
    }

    /** @return array{class-string<Model>|null, int|null} */
    private function resolveRelated(string $taskType, ?int $relatedId): array
    {
        if ($relatedId === null) {
            return [null, null];
        }

        $modelClass = match ($taskType) {
            'order' => Order::class,
            'merchant' => Merchant::class,
            'product' => Product::class,
            'payment' => Payment::class,
            'withdrawal' => WithdrawalRequest::class,
            'complaint', 'support' => ContactMessage::class,
            'delivery' => Delivery::class,
            default => null,
        };
        if ($modelClass === null || ! $modelClass::query()->whereKey($relatedId)->exists()) {
            throw ValidationException::withMessages([
                'related_id' => 'رقم السجل المرتبط لا يطابق نوع المهمة أو غير موجود.',
            ]);
        }

        return [(new $modelClass)->getMorphClass(), $relatedId];
    }

    private function assertTransition(StaffTaskStatus $from, StaffTaskStatus $to): void
    {
        $allowed = match ($from) {
            StaffTaskStatus::Assigned => [StaffTaskStatus::Assigned, StaffTaskStatus::InProgress, StaffTaskStatus::Waiting, StaffTaskStatus::Completed, StaffTaskStatus::Cancelled],
            StaffTaskStatus::InProgress => [StaffTaskStatus::InProgress, StaffTaskStatus::Waiting, StaffTaskStatus::Completed, StaffTaskStatus::Cancelled],
            StaffTaskStatus::Waiting => [StaffTaskStatus::Waiting, StaffTaskStatus::InProgress, StaffTaskStatus::Completed, StaffTaskStatus::Cancelled],
            StaffTaskStatus::Completed, StaffTaskStatus::Cancelled => [],
        };
        if (! in_array($to, $allowed, true)) {
            throw ValidationException::withMessages(['status' => 'انتقال حالة المهمة غير مسموح.']);
        }
    }

    /** @return array{assigned_to: int, priority: string, status: string, due_at: string|null, related_type: string|null, related_id: int|null, version: int} */
    private function auditState(StaffTask $task): array
    {
        return [
            'assigned_to' => $task->assigned_to,
            'priority' => $task->priority->value,
            'status' => $task->status->value,
            'due_at' => $task->due_at?->toIso8601String(),
            'related_type' => $task->related_type,
            'related_id' => $task->related_id,
            'version' => $task->version,
        ];
    }
}

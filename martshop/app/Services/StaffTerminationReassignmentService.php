<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\DeliveryStatus;
use App\Enums\StaffTaskStatus;
use App\Enums\SupportTicketStatus;
use App\Models\ContactMessage;
use App\Models\Delivery;
use App\Models\DeliveryEvent;
use App\Models\StaffTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffTerminationReassignmentService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function redistribute(User $actor, User $employee, array $options, string $reason): void
    {
        abort_unless($actor->hasPermission('roles.manage'), 403);
        $this->tasks($actor, $employee, $options['tasks_destination'] ?? null, $options['reassign_tasks_to'] ?? null, $reason);
        $this->chats($actor, $employee, $options['chats_destination'] ?? null, $options['reassign_chats_to'] ?? null, $reason);
        $this->deliveries($actor, $employee, $options['deliveries_destination'] ?? null, $options['reassign_deliveries_to'] ?? null, $reason);
    }

    private function destination(User $employee, ?string $strategy, ?int $replacementId, string $field, ?string $permission = null, ?string $role = null): array
    {
        if (! $strategy) throw ValidationException::withMessages([$field => 'اختر وجهة العمل النشط قبل إنهاء الخدمة.']);
        if ($strategy === 'department') {
            if (! $employee->staff_department_id) throw ValidationException::withMessages([$field => 'الموظف غير مرتبط بقسم يصلح كطابور.']);
            return [null, $employee->staff_department_id];
        }
        if ($strategy === 'unassigned') return [null, null];
        $replacement = $replacementId ? User::query()->whereKey($replacementId)->where('account_status', AccountStatus::Active->value)->lockForUpdate()->first() : null;
        if (! $replacement || ($permission && ! $replacement->hasPermission($permission)) || ($role && ! $replacement->hasRole($role))) {
            throw ValidationException::withMessages([$field => 'اختر موظفًا بديلًا نشطًا ومصرحًا لهذا النوع من العمل.']);
        }
        return [$replacement->id, null];
    }

    private function tasks(User $actor, User $employee, ?string $strategy, ?int $replacementId, string $reason): void
    {
        $tasks = StaffTask::query()->where('assigned_to', $employee->id)->whereNotIn('status', [StaffTaskStatus::Completed->value, StaffTaskStatus::Cancelled->value])->lockForUpdate()->get();
        if ($tasks->isEmpty()) return;
        [$assignee, $department] = $this->destination($employee, $strategy ?: ($replacementId ? 'specific' : null), $replacementId, 'reassign_tasks_to');
        foreach ($tasks as $task) {
            $before = ['assigned_to' => $task->assigned_to, 'status' => $task->status->value, 'version' => $task->version];
            $task->forceFill(['assigned_to' => $assignee, 'queue_department_id' => $department, 'status' => StaffTaskStatus::Assigned, 'version' => $task->version + 1])->save();
            $this->audit->record('staff.task_reassigned_on_termination', $task, $before, ['assigned_to' => $assignee, 'queue_department_id' => $department, 'status' => StaffTaskStatus::Assigned->value, 'version' => $task->version], $reason);
        }
    }

    private function chats(User $actor, User $employee, ?string $strategy, ?int $replacementId, string $reason): void
    {
        $tickets = ContactMessage::query()->where('assigned_to', $employee->id)->whereNotIn('status', [SupportTicketStatus::Resolved->value, SupportTicketStatus::Closed->value])->lockForUpdate()->get();
        if ($tickets->isEmpty()) return;
        [$assignee, $department] = $this->destination($employee, $strategy, $replacementId, 'chats_destination', 'contact-messages.manage');
        foreach ($tickets as $ticket) {
            $before = ['assigned_to' => $ticket->assigned_to, 'status' => $ticket->status->value, 'lock_version' => $ticket->lock_version];
            $ticket->forceFill(['assigned_to' => $assignee, 'queue_department_id' => $department, 'status' => $assignee ? SupportTicketStatus::Assigned : SupportTicketStatus::Open, 'lock_version' => $ticket->lock_version + 1])->save();
            $this->audit->record('support.ticket_reassigned_on_termination', $ticket, $before, ['assigned_to' => $assignee, 'queue_department_id' => $department, 'status' => $ticket->status->value, 'lock_version' => $ticket->lock_version], $reason);
        }
    }

    private function deliveries(User $actor, User $employee, ?string $strategy, ?int $replacementId, string $reason): void
    {
        $deliveries = Delivery::query()->where('delivery_worker_id', $employee->id)->whereNotIn('status', collect(DeliveryStatus::cases())->filter->isTerminal()->map->value)->lockForUpdate()->get();
        if ($deliveries->isEmpty()) return;
        [$assignee, $department] = $this->destination($employee, $strategy, $replacementId, 'deliveries_destination', role: 'delivery-worker');
        if (! $assignee && $deliveries->contains(fn ($delivery) => $delivery->status !== DeliveryStatus::Assigned)) {
            throw ValidationException::withMessages(['deliveries_destination' => 'التوصيل المقبول أو الجاري يجب نقله إلى مندوب محدد، ولا يمكن إعادته للطابور.']);
        }
        foreach ($deliveries as $delivery) {
            $from = $delivery->status;
            $delivery->forceFill(['delivery_worker_id' => $assignee, 'queue_department_id' => $department, 'status' => $assignee ? $from : DeliveryStatus::Unassigned, 'lock_version' => $delivery->lock_version + 1])->save();
            DeliveryEvent::query()->create(['delivery_id' => $delivery->id, 'event_type' => 'reassigned_on_termination', 'from_status' => $from->value, 'to_status' => $delivery->status->value, 'actor_id' => $actor->id, 'note' => $reason, 'metadata' => ['previous_worker_id' => $employee->id, 'delivery_worker_id' => $assignee, 'queue_department_id' => $department], 'created_at' => now()]);
            $this->audit->record('delivery.reassigned_on_termination', $delivery, ['delivery_worker_id' => $employee->id], ['delivery_worker_id' => $assignee, 'queue_department_id' => $department, 'status' => $delivery->status->value], $reason);
        }
    }
}

<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\SupportTicketStatus;
use App\Models\ContactMessage;
use App\Models\ContactMessageReply;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupportTicketService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function assign(User $actor, int $ticketId, int $assigneeId, int $expectedVersion): ContactMessage
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($ticketId, $assigneeId, $expectedVersion) {
            $ticket = $this->lockedTicket($ticketId, $expectedVersion);
            $assignee = User::query()->lockForUpdate()->findOrFail($assigneeId);
            if ($assignee->account_status !== AccountStatus::Active || ! $assignee->hasPermission('contact-messages.manage')) {
                throw ValidationException::withMessages(['assigned_to' => 'يجب إسناد التذكرة إلى موظف دعم نشط ومصرح.']);
            }

            $before = $this->auditState($ticket);
            $ticket->forceFill([
                'assigned_to' => $assignee->id,
                'assigned_at' => now(),
                'status' => $ticket->status === SupportTicketStatus::Open
                    ? SupportTicketStatus::Assigned : $ticket->status,
                'lock_version' => $ticket->lock_version + 1,
            ])->save();
            $this->audit->record('support.ticket_assigned', $ticket, $before, $this->auditState($ticket));

            return $ticket;
        });
    }

    public function reply(User $actor, int $ticketId, string $body, bool $internal, int $expectedVersion): ContactMessageReply
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($actor, $ticketId, $body, $internal, $expectedVersion) {
            $ticket = $this->lockedTicket($ticketId, $expectedVersion);
            if ($ticket->status === SupportTicketStatus::Closed) {
                throw ValidationException::withMessages(['ticket' => 'أعد فتح التذكرة قبل إضافة رد جديد.']);
            }
            if ($ticket->assigned_to !== null && $ticket->assigned_to !== $actor->id) {
                throw ValidationException::withMessages(['ticket' => 'التذكرة مسندة إلى موظف دعم آخر.']);
            }

            $before = $this->auditState($ticket);
            $reply = $ticket->replies()->create([
                'author_id' => $actor->id,
                'body' => trim($body),
                'is_internal' => $internal,
            ]);
            $ticket->forceFill([
                'assigned_to' => $ticket->assigned_to ?? $actor->id,
                'assigned_at' => $ticket->assigned_at ?? now(),
                'first_response_at' => ! $internal ? ($ticket->first_response_at ?? now()) : $ticket->first_response_at,
                'status' => in_array($ticket->status, [SupportTicketStatus::Open, SupportTicketStatus::Assigned], true)
                    ? SupportTicketStatus::InProgress : $ticket->status,
                'lock_version' => $ticket->lock_version + 1,
            ])->save();
            $this->audit->record(
                'support.ticket_replied',
                $ticket,
                $before,
                $this->auditState($ticket),
                metadata: ['reply_id' => $reply->id, 'internal' => $internal],
            );

            return $reply;
        });
    }

    public function transition(User $actor, int $ticketId, SupportTicketStatus $status, int $expectedVersion): ContactMessage
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($actor, $ticketId, $status, $expectedVersion) {
            $ticket = $this->lockedTicket($ticketId, $expectedVersion);
            $this->assertTransition($ticket->status, $status);
            $before = $this->auditState($ticket);
            $reopening = in_array($ticket->status, [SupportTicketStatus::Resolved, SupportTicketStatus::Closed], true)
                && $status === SupportTicketStatus::InProgress;
            $ticket->forceFill([
                'status' => $status,
                'resolved_at' => $status === SupportTicketStatus::Resolved ? now() : ($reopening ? null : $ticket->resolved_at),
                'resolved_by' => $status === SupportTicketStatus::Resolved ? $actor->id : ($reopening ? null : $ticket->resolved_by),
                'closed_at' => $status === SupportTicketStatus::Closed ? now() : ($reopening ? null : $ticket->closed_at),
                'closed_by' => $status === SupportTicketStatus::Closed ? $actor->id : ($reopening ? null : $ticket->closed_by),
                'reopened_count' => $ticket->reopened_count + ($reopening ? 1 : 0),
                'lock_version' => $ticket->lock_version + 1,
            ])->save();
            $this->audit->record('support.ticket_status_changed', $ticket, $before, $this->auditState($ticket));

            return $ticket;
        });
    }

    private function authorize(User $actor): void
    {
        abort_unless($actor->hasPermission('contact-messages.manage'), 403);
    }

    private function lockedTicket(int $ticketId, int $expectedVersion): ContactMessage
    {
        $ticket = ContactMessage::query()->lockForUpdate()->findOrFail($ticketId);
        if ($ticket->lock_version !== $expectedVersion) {
            throw ValidationException::withMessages(['ticket' => 'تغيرت التذكرة منذ فتح الصفحة. حدّث الصفحة وحاول مجددًا.']);
        }

        return $ticket;
    }

    private function assertTransition(SupportTicketStatus $from, SupportTicketStatus $to): void
    {
        $allowed = match ($from) {
            SupportTicketStatus::Open => [SupportTicketStatus::Open, SupportTicketStatus::Assigned, SupportTicketStatus::InProgress],
            SupportTicketStatus::Assigned => [SupportTicketStatus::Assigned, SupportTicketStatus::InProgress, SupportTicketStatus::WaitingCustomer, SupportTicketStatus::Resolved],
            SupportTicketStatus::InProgress => [SupportTicketStatus::InProgress, SupportTicketStatus::WaitingCustomer, SupportTicketStatus::Resolved],
            SupportTicketStatus::WaitingCustomer => [SupportTicketStatus::WaitingCustomer, SupportTicketStatus::InProgress, SupportTicketStatus::Resolved],
            SupportTicketStatus::Resolved => [SupportTicketStatus::Resolved, SupportTicketStatus::Closed, SupportTicketStatus::InProgress],
            SupportTicketStatus::Closed => [SupportTicketStatus::Closed, SupportTicketStatus::InProgress],
        };
        if (! in_array($to, $allowed, true)) {
            throw ValidationException::withMessages(['status' => 'انتقال حالة التذكرة غير مسموح.']);
        }
    }

    private function auditState(ContactMessage $ticket): array
    {
        return [
            'assigned_to' => $ticket->assigned_to,
            'status' => $ticket->status->value,
            'first_response_at' => $ticket->first_response_at?->toIso8601String(),
            'resolved_at' => $ticket->resolved_at?->toIso8601String(),
            'resolved_by' => $ticket->resolved_by,
            'closed_at' => $ticket->closed_at?->toIso8601String(),
            'closed_by' => $ticket->closed_by,
            'reopened_count' => $ticket->reopened_count,
            'lock_version' => $ticket->lock_version,
        ];
    }
}

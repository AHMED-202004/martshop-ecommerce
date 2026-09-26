<?php

namespace App\Services;

use App\Models\ContactMessage;
use App\Models\ContactMessageReply;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SupportPerformanceService
{
    public function __construct(private readonly PerformancePeriod $periods) {}
    /** @return array{period: string, assigned: int, handled: int, public_replies: int, resolved: int, closed: int, reopened: int, sla_breaches: int, average_first_response_minutes: float|null, average_resolution_minutes: float|null, history: Collection} */
    public function summary(User $actor, User $worker, string $period, ?string $from = null, ?string $to = null): array
    {
        abort_unless($actor->hasPermission('roles.manage'), 403);
        abort_unless($worker->hasPermission('contact-messages.manage'), 404);

        [$start, $end] = $this->periods->resolve($period, $from, $to);

        $assigned = ContactMessage::query()
            ->where('assigned_to', $worker->id)
            ->whereBetween('assigned_at', [$start, $end]);
        $handledTicketIds = ContactMessageReply::query()
            ->where('author_id', $worker->id)
            ->where('is_internal', false)
            ->whereBetween('created_at', [$start, $end])
            ->distinct()
            ->pluck('contact_message_id');
        $publicReplies = ContactMessageReply::query()
            ->where('author_id', $worker->id)
            ->where('is_internal', false)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $firstResponse = ['seconds' => 0, 'count' => 0];
        $resolution = ['seconds' => 0, 'count' => 0];
        (clone $assigned)
            ->select(['id', 'created_at', 'first_response_at', 'resolved_at'])
            ->orderBy('id')
            ->chunkById(500, function ($tickets) use (&$firstResponse, &$resolution) {
                foreach ($tickets as $ticket) {
                    $this->addDuration($firstResponse, $ticket->created_at, $ticket->first_response_at);
                    $this->addDuration($resolution, $ticket->created_at, $ticket->resolved_at);
                }
            });

        $now = now();
        $history = ContactMessage::query()
            ->select(['id', 'topic', 'status', 'assigned_to', 'assigned_at', 'first_response_at', 'resolved_at', 'closed_at', 'reopened_count', 'sla_due_at'])
            ->where(function ($tickets) use ($worker, $start, $end) {
                $tickets->where(function ($assigned) use ($worker, $start, $end) {
                    $assigned->where('assigned_to', $worker->id)->whereBetween('assigned_at', [$start, $end]);
                })->orWhereHas('replies', fn ($replies) => $replies
                    ->where('author_id', $worker->id)
                    ->where('is_internal', false)
                    ->whereBetween('created_at', [$start, $end]));
            })
            ->latest('id')
            ->limit(50)
            ->get();

        return [
            'period' => $period, 'from' => $start->toDateString(), 'to' => $end->toDateString(),
            'assigned' => (clone $assigned)->count(),
            'handled' => $handledTicketIds->count(),
            'public_replies' => $publicReplies,
            'resolved' => ContactMessage::query()->where('resolved_by', $worker->id)->whereBetween('resolved_at', [$start, $end])->count(),
            'closed' => ContactMessage::query()->where('closed_by', $worker->id)->whereBetween('closed_at', [$start, $end])->count(),
            'reopened' => (int) (clone $assigned)->sum('reopened_count'),
            'sla_breaches' => (clone $assigned)
                ->whereNotNull('sla_due_at')
                ->where(fn ($tickets) => $tickets
                    ->whereColumn('first_response_at', '>', 'sla_due_at')
                    ->orWhere(fn ($pending) => $pending->whereNull('first_response_at')->where('sla_due_at', '<', $now)))
                ->count(),
            'average_first_response_minutes' => $this->averageMinutes($firstResponse),
            'average_resolution_minutes' => $this->averageMinutes($resolution),
            'history' => $history,
        ];
    }

    /** @param array{seconds: int, count: int} $bucket */
    private function addDuration(array &$bucket, ?Carbon $start, ?Carbon $end): void
    {
        if (! $start || ! $end || $end->lessThan($start)) {
            return;
        }

        $bucket['seconds'] += $end->timestamp - $start->timestamp;
        $bucket['count']++;
    }

    /** @param array{seconds: int, count: int} $bucket */
    private function averageMinutes(array $bucket): ?float
    {
        return $bucket['count'] > 0 ? round($bucket['seconds'] / $bucket['count'] / 60, 1) : null;
    }
}

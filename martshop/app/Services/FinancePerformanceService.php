<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\User;

class FinancePerformanceService
{
    public function __construct(private readonly PerformancePeriod $periods) {}
    public function summary(User $actor, User $worker, string $period, ?string $from = null, ?string $to = null): array
    {
        abort_unless($actor->hasPermission('roles.manage'), 403);
        abort_unless($worker->hasPermission('payments.verify'), 404);
        [$start, $end] = $this->periods->resolve($period, $from, $to);
        $totals = ['reviewed' => 0, 'accepted' => 0, 'rejected' => 0, 'escalated' => 0, 'corrections' => 0, 'reconciliation_discrepancies' => 0];
        $duration = ['seconds' => 0, 'count' => 0];
        Payment::query()->select(['id', 'status', 'created_at', 'reviewed_at'])
            ->where('reviewed_by', $worker->id)->whereBetween('reviewed_at', [$start, $end])
            ->orderBy('id')->chunkById(500, function ($payments) use (&$totals, &$duration) {
                foreach ($payments as $payment) {
                    $status = $payment->status->value;
                    $totals['reviewed']++;
                    $totals['accepted'] += $status === 'accepted' ? 1 : 0;
                    $totals['rejected'] += $status === 'rejected' ? 1 : 0;
                    $totals['escalated'] += $status === 'suspicious' ? 1 : 0;
                    $totals['corrections'] += in_array($status, ['short_amount', 'overpaid'], true) ? 1 : 0;
                    $totals['reconciliation_discrepancies'] += in_array($status, ['short_amount', 'overpaid', 'duplicate'], true) ? 1 : 0;
                    if ($payment->reviewed_at && $payment->reviewed_at->gte($payment->created_at)) {
                        $duration['seconds'] += $payment->reviewed_at->timestamp - $payment->created_at->timestamp;
                        $duration['count']++;
                    }
                }
            });

        return [
            'period' => $period, 'from' => $start->toDateString(), 'to' => $end->toDateString(), ...$totals,
            'average_review_minutes' => $duration['count'] ? round($duration['seconds'] / $duration['count'] / 60, 1) : null,
            'pending_workload' => Payment::query()->where('status', 'pending')->count(),
        ];
    }
}

<?php

namespace App\Services;

use App\Models\CatalogReviewDecision;
use App\Models\Product;
use App\Models\ProductChangeRequest;
use App\Models\ProductOffer;
use App\Models\User;

class ProductModeratorPerformanceService
{
    public function __construct(private readonly PerformancePeriod $periods) {}
    public function summary(User $actor, User $worker, string $period, ?string $from = null, ?string $to = null): array
    {
        abort_unless($actor->hasPermission('roles.manage'), 403);
        abort_unless($worker->hasPermission('products.moderate'), 404);
        [$start, $end] = $this->periods->resolve($period, $from, $to);
        $totals = ['reviewed' => 0, 'approved' => 0, 'rejected' => 0, 'changes_requested' => 0];
        $duration = ['seconds' => 0, 'count' => 0];
        CatalogReviewDecision::query()->select(['id', 'to_status', 'submitted_at', 'decided_at'])
            ->where('reviewer_id', $worker->id)->whereBetween('decided_at', [$start, $end])
            ->orderBy('id')->chunkById(500, function ($records) use (&$totals, &$duration) {
                foreach ($records as $record) {
                    $totals['reviewed']++;
                    $totals['approved'] += in_array($record->to_status, ['active', 'approved'], true) ? 1 : 0;
                    $totals['rejected'] += $record->to_status === 'rejected' ? 1 : 0;
                    $totals['changes_requested'] += $record->to_status === 'changes_requested' ? 1 : 0;
                    if ($record->submitted_at && $record->decided_at->gte($record->submitted_at)) {
                        $duration['seconds'] += $record->decided_at->timestamp - $record->submitted_at->timestamp;
                        $duration['count']++;
                    }
                }
            });

        return [
            'period' => $period, 'from' => $start->toDateString(), 'to' => $end->toDateString(),
            ...$totals,
            'average_review_minutes' => $duration['count'] ? round($duration['seconds'] / $duration['count'] / 60, 1) : null,
            'reversed_decisions' => CatalogReviewDecision::query()->where('reviewer_id', $worker->id)
                ->whereBetween('decided_at', [$start, $end])->where('is_reversal', true)->count(),
            'pending_workload' => Product::where('status', 'pending_review')->count()
                + ProductOffer::where('status', 'pending_review')->count()
                + ProductChangeRequest::where('status', 'pending_review')->count(),
        ];
    }
}

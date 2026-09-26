<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DeliveryPerformanceService
{
    public function __construct(private readonly PerformancePeriod $periods) {}
    /** @return array{period: string, assigned: int, accepted: int, delivered: int, active: int, average_response_minutes: float|null, average_pickup_minutes: float|null, average_travel_minutes: float|null, average_total_minutes: float|null, history: Collection} */
    public function summary(User $actor, User $worker, string $period, ?string $from = null, ?string $to = null, ?string $area = null, ?DeliveryStatus $status = null): array
    {
        abort_unless($actor->hasPermission('roles.manage'), 403);
        abort_unless($worker->hasRole('delivery-worker'), 404);

        [$start, $end] = $this->periods->resolve($period, $from, $to);
        $totals = [
            'assigned' => 0,
            'accepted' => 0,
            'delivered' => 0,
            'active' => 0,
            'failed' => 0,
            'returned' => 0,
            'cancelled' => 0,
            'on_time' => 0,
            'late' => 0,
            'worker_responsible_late' => 0,
            'external_responsibility_late' => 0,
        ];
        $durations = [
            'response' => ['seconds' => 0, 'count' => 0],
            'pickup' => ['seconds' => 0, 'count' => 0],
            'travel' => ['seconds' => 0, 'count' => 0],
            'total' => ['seconds' => 0, 'count' => 0],
            'to_merchant' => ['seconds' => 0, 'count' => 0],
            'merchant_wait' => ['seconds' => 0, 'count' => 0],
            'delivery_travel' => ['seconds' => 0, 'count' => 0],
            'doorstep' => ['seconds' => 0, 'count' => 0],
        ];
        $ratingTotal = 0;
        $ratingCount = 0;

        $scope = fn ($query) => $query->where('delivery_worker_id', $worker->id)
            ->whereBetween('assigned_at', [$start, $end])
            ->when($status, fn ($q) => $q->where('status', $status->value))
            ->when($area, fn ($q) => $q->where(fn ($areas) => $areas->where('destination_snapshot->city', $area)->orWhere('destination_snapshot->governorate', $area)));
        Delivery::query()
            ->select(['id', 'status', 'assigned_at', 'accepted_at', 'heading_to_merchant_at', 'merchant_arrived_at', 'picked_up_at', 'out_for_delivery_at', 'customer_arrived_at', 'delivered_at', 'expected_delivery_at', 'delay_responsibility'])
            ->with('rating:id,delivery_id,rating')
            ->tap($scope)
            ->orderBy('id')
            ->chunkById(500, function ($deliveries) use (&$totals, &$durations, &$ratingTotal, &$ratingCount) {
                foreach ($deliveries as $delivery) {
                    $totals['assigned']++;
                    $totals['accepted'] += $delivery->accepted_at ? 1 : 0;
                    $totals['delivered'] += $delivery->status === DeliveryStatus::Delivered ? 1 : 0;
                    $totals['active'] += ! $delivery->status->isTerminal() ? 1 : 0;
                    $totals['failed'] += $delivery->status === DeliveryStatus::Failed ? 1 : 0;
                    $totals['returned'] += $delivery->status === DeliveryStatus::Returned ? 1 : 0;
                    $totals['cancelled'] += $delivery->status === DeliveryStatus::Cancelled ? 1 : 0;
                    if ($delivery->rating) { $ratingTotal += $delivery->rating->rating; $ratingCount++; }
                    if ($delivery->status === DeliveryStatus::Delivered && $delivery->expected_delivery_at) {
                        $totals[$delivery->delivered_at->lte($delivery->expected_delivery_at) ? 'on_time' : 'late']++;
                        if ($delivery->delivered_at->gt($delivery->expected_delivery_at)) {
                            $totals[$delivery->delay_responsibility === 'delivery_worker' ? 'worker_responsible_late' : 'external_responsibility_late']++;
                        }
                    }
                    $this->addDuration($durations['response'], $delivery->assigned_at, $delivery->accepted_at);
                    $this->addDuration($durations['pickup'], $delivery->accepted_at, $delivery->picked_up_at);
                    $this->addDuration($durations['travel'], $delivery->picked_up_at, $delivery->delivered_at);
                    $this->addDuration($durations['total'], $delivery->assigned_at, $delivery->delivered_at);
                    $this->addDuration($durations['to_merchant'], $delivery->heading_to_merchant_at ?? $delivery->accepted_at, $delivery->merchant_arrived_at);
                    $this->addDuration($durations['merchant_wait'], $delivery->merchant_arrived_at, $delivery->picked_up_at);
                    $this->addDuration($durations['delivery_travel'], $delivery->out_for_delivery_at ?? $delivery->picked_up_at, $delivery->customer_arrived_at);
                    $this->addDuration($durations['doorstep'], $delivery->customer_arrived_at, $delivery->delivered_at);
                }
            });

        $history = Delivery::query()
            ->select(['id', 'order_id', 'merchant_order_id', 'reference', 'status', 'origin_snapshot', 'destination_snapshot', 'assigned_at', 'accepted_at', 'heading_to_merchant_at', 'merchant_arrived_at', 'picked_up_at', 'out_for_delivery_at', 'customer_arrived_at', 'in_transit_at', 'delivered_at', 'expected_delivery_at', 'delay_reason', 'delay_responsibility'])
            ->with('rating:id,delivery_id,rating')
            ->tap($scope)
            ->latest('assigned_at')
            ->limit(50)
            ->get();

        $deadlineCount = $totals['on_time'] + $totals['late'];
        $completionBase = $totals['delivered'] + $totals['failed'] + $totals['returned'];
        $onTimeRate = $deadlineCount > 0 ? round($totals['on_time'] * 100 / $deadlineCount, 1) : null;
        $accountableDeadlineCount = $totals['on_time'] + $totals['worker_responsible_late'];
        $scoreOnTimeRate = $accountableDeadlineCount > 0 ? round($totals['on_time'] * 100 / $accountableDeadlineCount, 1) : null;
        $completionRate = $completionBase > 0 ? round($totals['delivered'] * 100 / $completionBase, 1) : null;
        $failedRate = $completionBase > 0 ? round($totals['failed'] * 100 / $completionBase, 1) : null;
        $averageRating = $ratingCount > 0 ? round($ratingTotal / $ratingCount, 2) : null;

        return [
            'period' => $period,
            'from' => $start->toDateString(), 'to' => $end->toDateString(), 'area' => $area, 'status_filter' => $status?->value,
            ...$totals,
            'average_response_minutes' => $this->averageMinutes($durations['response']),
            'average_pickup_minutes' => $this->averageMinutes($durations['pickup']),
            'average_travel_minutes' => $this->averageMinutes($durations['travel']),
            'average_total_minutes' => $this->averageMinutes($durations['total']),
            'average_to_merchant_minutes' => $this->averageMinutes($durations['to_merchant']),
            'average_merchant_wait_minutes' => $this->averageMinutes($durations['merchant_wait']),
            'average_delivery_travel_minutes' => $this->averageMinutes($durations['delivery_travel']),
            'average_doorstep_minutes' => $this->averageMinutes($durations['doorstep']),
            'on_time_rate' => $onTimeRate,
            'completion_rate' => $completionRate,
            'failed_rate' => $failedRate,
            'average_rating' => $averageRating,
            'rating_count' => $ratingCount,
            'score' => $this->score($scoreOnTimeRate, $completionRate, $failedRate, $averageRating),
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

    private function score(?float $onTime, ?float $completion, ?float $failed, ?float $rating): ?float
    {
        $parts = array_filter([
            $onTime === null ? null : [$onTime, 35],
            $completion === null ? null : [$completion, 35],
            $failed === null ? null : [100 - $failed, 15],
            $rating === null ? null : [$rating * 20, 15],
        ]);
        if (count($parts) < 2) return null;
        $weight = array_sum(array_column($parts, 1));
        return round(array_sum(array_map(fn ($part) => $part[0] * $part[1], $parts)) / $weight, 1);
    }
}

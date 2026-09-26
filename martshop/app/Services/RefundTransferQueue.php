<?php

namespace App\Services;

use App\Enums\RefundStatus;
use App\Models\RefundRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class RefundTransferQueue
{
    public function data(array $filters): array
    {
        $query = RefundRequest::query()->whereIn('status', [
            RefundStatus::Approved->value, RefundStatus::Processing->value, RefundStatus::Paid->value,
        ]);
        $search = trim($filters['q'] ?? '');
        if ($search !== '') {
            // Literal reference search: user input must not become LIKE wildcards.
            $query->whereRaw("reference LIKE ? ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%']);
        }
        $query->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status));
        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }
        if (! empty($filters['to'])) {
            // Exclusive next midnight includes the whole selected day, including fractional seconds.
            $query->where('created_at', '<', Carbon::parse($filters['to'])->addDay()->startOfDay());
        }
        if (! empty($filters['cancelled_only'])) {
            $query->whereHas('transfers', fn (Builder $attempts) => $attempts->whereNotNull('cancelled_at'));
        }

        // Aggregate parent refunds, never joined attempt rows: retries must not multiply totals.
        $summary = (clone $query)->select('status', 'currency')
            ->selectRaw('COUNT(*) AS refund_count, SUM(amount) AS total_minor')
            ->groupBy('status', 'currency')->orderBy('status')->orderBy('currency')->get();
        $direction = ($filters['sort'] ?? 'newest') === 'oldest' ? 'asc' : 'desc';
        $refunds = $query->select(['id', 'reference', 'status', 'amount', 'currency', 'created_at'])
            ->with(['activeDestination:id,refund_request_id,status,reviewed_at',
                'transfer:id,refund_request_id,prepared_at,paid_at'])
            ->withCount(['transfers as cancelled_attempts_count' => fn (Builder $attempts) => $attempts->whereNotNull('cancelled_at')])
            ->orderBy('created_at', $direction)->orderBy('id', $direction)
            ->paginate(30)->appends(array_diff_key($filters, ['page' => true]));

        return ['refunds' => $refunds, 'summary' => $summary, 'filters' => $filters];
    }
}

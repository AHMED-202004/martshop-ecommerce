<?php
namespace App\Services;
use App\Models\CatalogReviewDecision;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
class CatalogReviewDecisionRecorder
{
    public function record(Model $subject, User $reviewer, string $from, string $to, ?Carbon $submittedAt, string $origin = 'direct'): CatalogReviewDecision
    {
        return CatalogReviewDecision::query()->create(['subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey(), 'reviewer_id' => $reviewer->id, 'from_status' => $from, 'to_status' => $to, 'origin' => $origin, 'submitted_at' => $submittedAt, 'decided_at' => now(), 'is_reversal' => $this->isReversal($from, $to)]);
    }
    private function isReversal(string $from, string $to): bool
    {
        return in_array([$from, $to], [['active', 'hidden'], ['hidden', 'active'], ['active', 'paused'], ['paused', 'active'], ['rejected', 'changes_requested']], true);
    }
}

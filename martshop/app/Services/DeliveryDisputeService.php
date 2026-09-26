<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\DeliveryDispute;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryDisputeService
{
    public function __construct(private readonly MerchantLedgerService $ledger, private readonly AuditLogger $audit) {}

    public function open(Delivery $delivery, User $actor, string $reason): DeliveryDispute
    {
        return DB::transaction(function () use ($delivery, $actor, $reason) {
            $locked = Delivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            $isOwner = $locked->order->user_id === $actor->id;
            $staff = $actor->hasPermission('settlements.manage') && ! $isOwner;
            abort_unless($staff || $isOwner, 403);
            $existing = $locked->dispute()->first();
            if ($existing?->status === 'open') {
                return $existing;
            }
            if ($existing || $locked->settled_at || $locked->status !== DeliveryStatus::Delivered
                || ! $locked->settlement_due_at || (! $staff && $locked->settlement_due_at->lte(now()))) {
                throw ValidationException::withMessages(['dispute' => 'لا يمكن فتح نزاع لهذه المهمة حاليًا.']);
            }
            $dispute = DeliveryDispute::query()->create([
                'delivery_id' => $locked->id, 'opened_by' => $actor->id,
                'reason' => $reason, 'status' => 'open',
            ]);
            $this->ledger->holdDisputedSale($locked, $actor);
            $this->audit->record('delivery.dispute_opened', $dispute, null, [
                'delivery_id' => $locked->id, 'status' => 'open',
            ], $reason);

            return $dispute;
        }, 3);
    }

    public function close(Delivery $delivery, User $actor, string $reason): DeliveryDispute
    {
        abort_unless($actor->hasPermission('settlements.manage'), 403);

        return DB::transaction(function () use ($delivery, $actor, $reason) {
            $locked = Delivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->order->user_id === $actor->id, 403);
            $dispute = $locked->dispute()->firstOrFail();
            if ($dispute->refundRequest && $dispute->refundRequest->status !== \App\Enums\RefundStatus::Rejected) {
                throw ValidationException::withMessages(['dispute' => 'لا يمكن رفع الحجز مع وجود طلب استرداد معلّق أو موافق عليه ولم يُنفّذ.']);
            }
            if ($dispute->status === 'closed') {
                return $dispute;
            }
            if ($locked->settled_at) {
                throw ValidationException::withMessages(['dispute' => 'تعارض في حالة التسوية؛ تلزم مراجعة مالية.']);
            }
            $this->ledger->restoreDisputedSale($locked, $actor);
            $dispute->update([
                'status' => 'closed', 'closed_by' => $actor->id, 'close_reason' => $reason, 'closed_at' => now(),
            ]);
            $this->audit->record('delivery.dispute_closed', $dispute, ['status' => 'open'], ['status' => 'closed'], $reason);

            return $dispute;
        }, 3);
    }
}

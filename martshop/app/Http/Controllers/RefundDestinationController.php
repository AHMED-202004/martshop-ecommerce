<?php

namespace App\Http\Controllers;

use App\Enums\RefundStatus;
use App\Http\Requests\{ChangeRefundDestinationRequest, StoreRefundDestinationRequest};
use App\Models\{RefundDestination, RefundRequest};
use App\Services\{AuditLogger, RefundDestinationService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RefundDestinationController extends Controller
{
    public function index(Request $request, RefundRequest $refundRequest)
    {
        abort_unless($this->ownedBy($refundRequest, $request->user()->id), 403);
        $refundRequest->load([
            'dispute:id,delivery_id,status',
            'dispute.delivery:id,settled_at',
            'activeDestination:id,refund_request_id,last_four,status,lock_version',
        ]);

        return view('refund-destinations.customer', [
            'refund' => $refundRequest, 'active' => $refundRequest->activeDestination,
            'destinations' => $refundRequest->destinations()
                ->select(['id', 'refund_request_id', 'last_four', 'status', 'lock_version', 'created_at'])
                ->latest('id')->paginate(10),
            'canChange' => in_array($refundRequest->status, [RefundStatus::Requested, RefundStatus::Approved], true)
                && $refundRequest->dispute->status === 'open' && ! $refundRequest->dispute->delivery->settled_at,
        ]);
    }

    public function store(StoreRefundDestinationRequest $request, RefundRequest $refundRequest, RefundDestinationService $service)
    {
        $service->submit($refundRequest, $request->user(), $request->validated(), (int) $request->validated('lock_version'));

        return redirect()->route('refund-destinations.index', $refundRequest)->with('success', 'سُجّلت الوسيلة للتحقق اليدوي. لم يُحوّل أي مبلغ.');
    }

    public function show(RefundDestination $refundDestination, AuditLogger $audit)
    {
        Gate::authorize('view', $refundDestination);
        $refundDestination->load([
            'refund:id,delivery_dispute_id,reference,status',
            'refund.dispute:id,delivery_id,status',
            'refund.dispute.delivery:id,order_id,settled_at',
            'refund.dispute.delivery.order:id,user_id',
        ]);
        $audit->record('refund_destination.viewed', $refundDestination);

        return view('refund-destinations.show', ['destination' => $refundDestination]);
    }

    public function revoke(ChangeRefundDestinationRequest $request, RefundDestination $refundDestination, RefundDestinationService $service)
    {
        $service->revoke($refundDestination, $request->user(), (int) $request->validated('lock_version'));

        return redirect()->route('refund-destinations.index', $refundDestination->refund_request_id)
            ->with('success', 'أُلغيت الوسيلة مع حفظ تاريخها. أي وسيلة جديدة تحتاج تحققًا جديدًا.');
    }

    private function ownedBy(RefundRequest $refund, int $userId): bool
    {
        return RefundRequest::query()->whereKey($refund->id)
            ->whereHas('dispute.delivery.order', fn ($query) => $query->where('user_id', $userId))
            ->exists();
    }
}

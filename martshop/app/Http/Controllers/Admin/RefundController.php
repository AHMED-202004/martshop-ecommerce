<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RefundStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewRefundRequest;
use App\Models\RefundRequest;
use App\Services\RefundReviewService;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('refunds.review'), 403);

        return view('admin.refunds.index', [
            'refunds' => RefundRequest::query()
                ->where('requested_by', '!=', $request->user()->id)
                ->select(['id', 'delivery_dispute_id', 'reference', 'status', 'amount', 'currency',
                    'amount_snapshot', 'reason', 'review_reason', 'reviewed_at', 'lock_version'])
                ->with([
                    'dispute:id,delivery_id,reason',
                    'dispute.delivery:id,merchant_order_id,reference',
                    'activeDestination:id,refund_request_id,status',
                ])->latest('id')->paginate(30),
        ]);
    }

    public function review(ReviewRefundRequest $request, RefundRequest $refundRequest, RefundReviewService $service)
    {
        $data = $request->validated();
        $service->review($refundRequest, $request->user(), RefundStatus::from($data['decision']),
            $data['reason'], (int) $data['lock_version']);

        return back()->with('success', 'تم تسجيل قرار المراجعة. لم يُحوّل أي مبلغ ولم يُرفع حجز النزاع.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RefundDestinationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewRefundDestinationRequest;
use App\Models\RefundDestination;
use App\Services\RefundDestinationService;
use Illuminate\Http\Request;

class RefundDestinationController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('refund-destinations.review'), 403);

        return view('refund-destinations.admin', [
            'destinations' => RefundDestination::query()
                ->where('submitted_by', '!=', $request->user()->id)
                ->select(['id', 'refund_request_id', 'last_four', 'status'])
                ->with('refund:id,reference,status')->latest('id')->paginate(30),
        ]);
    }

    public function review(ReviewRefundDestinationRequest $request, RefundDestination $refundDestination, RefundDestinationService $service)
    {
        $data = $request->validated();
        $service->review($refundDestination, $request->user(), RefundDestinationStatus::from($data['decision']),
            $data['review_notes'], (int) $data['lock_version'], $request->boolean('ownership_confirmed'));

        return redirect()->route('refund-destinations.show', $refundDestination)
            ->with('success', 'حُفظت نتيجة التحقق من الوسيلة. لم يتغير قرار الاسترداد ولم يُحوّل أي مبلغ.');
    }
}

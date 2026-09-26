<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRefundRequest;
use App\Models\Delivery;
use App\Services\RefundReviewService;

class RefundRequestController extends Controller
{
    public function store(StoreRefundRequest $request, Delivery $delivery, RefundReviewService $service)
    {
        $service->request($delivery, $request->user(), $request->validated('reason'));

        return back()->with('success', 'تم تسجيل طلب مراجعة الاسترداد الكامل. لا تعني هذه الخطوة تحويل المبلغ.');
    }
}

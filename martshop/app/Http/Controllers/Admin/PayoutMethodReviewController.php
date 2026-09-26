<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MerchantPayoutMethodStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewPayoutMethodRequest;
use App\Models\MerchantPayoutMethod;
use App\Services\MerchantPayoutMethodService;

class PayoutMethodReviewController extends Controller
{
    public function update(
        ReviewPayoutMethodRequest $request,
        MerchantPayoutMethod $payoutMethod,
        MerchantPayoutMethodService $methods,
    ) {
        $this->authorize('review', $payoutMethod);
        $methods->review(
            $payoutMethod,
            MerchantPayoutMethodStatus::from($request->validated('decision')),
            $request->user(),
            $request->validated('notes'),
            (int) $request->validated('lock_version'),
        );

        return back()->with('success', 'تم حفظ مراجعة وسيلة الاستلام.');
    }
}

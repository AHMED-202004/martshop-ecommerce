<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\DisablePayoutMethodRequest;
use App\Http\Requests\Merchant\StorePayoutMethodRequest;
use App\Models\MerchantPayoutMethod;
use App\Services\MerchantPayoutMethodService;

class PayoutMethodController extends Controller
{
    public function store(StorePayoutMethodRequest $request, MerchantPayoutMethodService $methods)
    {
        $methods->register($request->user()->merchant, $request->validated(), $request->user());

        return back()->with('success', 'تم إرسال وسيلة الاستلام للتحقق. لا يمكن استخدامها قبل اعتمادها.');
    }

    public function disable(
        DisablePayoutMethodRequest $request,
        MerchantPayoutMethod $payoutMethod,
        MerchantPayoutMethodService $methods,
    ) {
        $methods->disable($payoutMethod, $request->user());

        return back()->with('success', 'تم تعطيل وسيلة الاستلام.');
    }
}

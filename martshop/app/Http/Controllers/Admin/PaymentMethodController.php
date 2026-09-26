<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePaymentMethodRequest;
use App\Http\Requests\Admin\UpdatePaymentMethodRequest;
use App\Models\PaymentMethod;
use App\Services\PaymentMethodService;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('payment-methods.manage'), 403);

        return view('admin.payment-methods.index', [
            'methods' => PaymentMethod::query()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(StorePaymentMethodRequest $request, PaymentMethodService $methods)
    {
        $methods->create($request->validated(), $request->user());

        return back()->with('success', 'تمت إضافة وسيلة الدفع.');
    }

    public function update(
        UpdatePaymentMethodRequest $request,
        PaymentMethod $paymentMethod,
        PaymentMethodService $methods,
    ) {
        $methods->update($paymentMethod, $request->validated(), $request->user());

        return back()->with('success', 'تم تحديث وسيلة الدفع.');
    }
}

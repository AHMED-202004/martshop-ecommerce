<?php

namespace App\Http\Controllers;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Http\Requests\SubmitManualPaymentRequest;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Services\PaymentSubmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function create(Request $request, Order $order)
    {
        abort_unless($order->user_id === $request->user()->id, 403);
        $order->load(['payments' => fn ($query) => $query->with('method')->latest('id')]);

        if ($order->status !== OrderStatus::Confirmed) {
            return redirect()->route('orders.history')
                ->withErrors(['payment' => 'الدفع متاح بعد تأكيد جميع التجار للطلب.']);
        }
        if ($order->payment_status === OrderPaymentStatus::Paid) {
            return redirect()->route('orders.history')->with('success', 'الطلب مدفوع بالفعل.');
        }

        return view('payment.manual', [
            'order' => $order,
            'methods' => PaymentMethod::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'openPayment' => $order->payments->firstWhere('open_key', 'order:'.$order->id),
            'submissionKey' => old('idempotency_key', (string) Str::uuid()),
        ]);
    }

    public function store(
        SubmitManualPaymentRequest $request,
        Order $order,
        PaymentSubmissionService $payments,
    ) {
        $payment = $payments->submit(
            $order,
            $request->user(),
            $request->validated(),
            $request->file('proof'),
        );

        return redirect()->route('payments.create', $order)
            ->with('success', "تم إرسال إثبات الدفع رقم {$payment->id} للمراجعة.");
    }

    public function showCard(Request $request)
    {
        return redirect()->route('cart.index')
            ->withErrors(['payment' => 'الدفع بالبطاقة غير متاح حاليًا.']);
    }

    public function charge()
    {
        return response('Card payments are not configured.', 503);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewPaymentRequest;
use App\Models\Payment;
use App\Services\PaymentReviewService;
use Illuminate\Http\Request;

class PaymentReviewController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('payments.verify'), 403);

        $pending = Payment::query()
            ->select([
                'id', 'order_id', 'payment_method_id', 'amount', 'currency',
                'provider', 'provider_ref', 'status',
            ])
            ->where('status', PaymentStatus::Pending->value)
            ->with(['order:id,user_id', 'order.user:id,name', 'method:id,name'])
            ->latest('id')
            ->paginate(30, ['*'], 'pending_page');
        $recent = Payment::query()
            ->select(['id', 'order_id', 'status', 'reviewed_by', 'reviewed_at'])
            ->where('status', '!=', PaymentStatus::Pending->value)
            ->with(['reviewer:id,name'])
            ->latest('reviewed_at')
            ->limit(30)
            ->get();

        return view('admin.payments.index', compact('pending', 'recent'));
    }

    public function show(Payment $payment)
    {
        $this->authorize('view', $payment);
        $payment = Payment::query()->select([
            'id', 'order_id', 'payment_method_id', 'amount', 'currency', 'provider',
            'provider_ref', 'sender_name', 'sender_account', 'transferred_at', 'status',
            'meta', 'reviewed_by', 'reviewed_at', 'review_notes', 'lock_version',
        ])->whereKey($payment->id)->firstOrFail();
        $payment->load([
            'order:id,user_id,total',
            'order.user:id,name,email',
            'method:id,name',
            'proof:id,payment_id',
            'reviewer:id,name',
        ]);

        return view('admin.payments.show', compact('payment'));
    }

    public function update(
        ReviewPaymentRequest $request,
        Payment $payment,
        PaymentReviewService $payments,
    ) {
        $this->authorize('review', $payment);
        $payments->review(
            $payment,
            PaymentStatus::from($request->validated('decision')),
            $request->user(),
            $request->validated('reason'),
            (int) $request->validated('lock_version'),
        );

        return redirect()->route('admin.payments.show', $payment)
            ->with('success', 'تم حفظ قرار مراجعة الدفعة.');
    }
}

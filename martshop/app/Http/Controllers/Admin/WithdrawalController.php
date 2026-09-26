<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MerchantPayoutMethodStatus;
use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PayWithdrawalRequest;
use App\Http\Requests\Admin\ReviewWithdrawalRequest;
use App\Http\Requests\Admin\UpdateWithdrawalPolicyRequest;
use App\Models\MerchantPayoutMethod;
use App\Models\WithdrawalRequest;
use App\Services\WithdrawalPolicyService;
use App\Services\WithdrawalService;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    public function index(Request $request, WithdrawalPolicyService $policies)
    {
        abort_unless(
            $request->user()->hasPermission('withdrawals.approve')
                || $request->user()->hasPermission('withdrawals.settings'),
            403,
        );
        $filters = $request->validate([
            'status' => ['nullable', 'in:requested,approved,rejected,paid'],
        ]);
        $canReview = $request->user()->hasPermission('withdrawals.approve');

        return view('admin.withdrawals.index', [
            'canReview' => $canReview,
            'payoutMethods' => $canReview ? MerchantPayoutMethod::query()
                ->select([
                    'id', 'merchant_id', 'type', 'provider_name', 'account_name',
                    'account_identifier', 'status', 'lock_version',
                ])
                ->with(['merchant:id,user_id,legal_name'])
                ->where('status', MerchantPayoutMethodStatus::Pending->value)
                ->whereHas('merchant', fn ($query) => $query->where('user_id', '!=', $request->user()->id))
                ->oldest('id')->get() : collect(),
            'withdrawals' => $canReview ? WithdrawalRequest::query()
                ->select([
                    'id', 'merchant_id', 'merchant_payout_method_id', 'amount', 'currency',
                    'status', 'reference', 'requested_at', 'review_notes', 'transferred_at',
                    'transaction_reference', 'lock_version',
                ])
                ->with([
                    'merchant:id,user_id,legal_name',
                    'payoutMethod:id,merchant_id,provider_name,account_name,account_identifier',
                    'proof:id,withdrawal_request_id',
                ])
                ->whereHas('merchant', fn ($query) => $query->where('user_id', '!=', $request->user()->id))
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->latest('id')->paginate(40)->withQueryString() : null,
            'policy' => $policies->policy(),
        ]);
    }

    public function review(
        ReviewWithdrawalRequest $request,
        WithdrawalRequest $withdrawalRequest,
        WithdrawalService $withdrawals,
    ) {
        $this->authorize('review', $withdrawalRequest);
        $withdrawals->review(
            $withdrawalRequest,
            WithdrawalStatus::from($request->validated('decision')),
            $request->user(),
            $request->validated('notes'),
            (int) $request->validated('lock_version'),
        );

        return back()->with('success', 'تم حفظ قرار مراجعة طلب السحب.');
    }

    public function pay(
        PayWithdrawalRequest $request,
        WithdrawalRequest $withdrawalRequest,
        WithdrawalService $withdrawals,
    ) {
        $this->authorize('review', $withdrawalRequest);
        $withdrawals->markPaid(
            $withdrawalRequest,
            $request->validated(),
            $request->file('proof'),
            $request->user(),
        );

        return back()->with('success', 'تم تسجيل التحويل وإغلاق طلب السحب كمدفوع.');
    }

    public function updatePolicy(
        UpdateWithdrawalPolicyRequest $request,
        WithdrawalPolicyService $policies,
    ) {
        $policies->update($request->validated(), $request->user());

        return back()->with('success', 'تم تحديث سياسة وحدود السحب.');
    }
}

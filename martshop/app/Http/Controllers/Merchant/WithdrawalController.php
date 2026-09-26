<?php

namespace App\Http\Controllers\Merchant;

use App\Enums\MerchantPayoutMethodStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\StoreWithdrawalRequest;
use App\Models\MerchantPayoutMethod;
use App\Services\MerchantLedgerService;
use App\Services\WithdrawalPolicyService;
use App\Services\WithdrawalService;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    public function index(
        Request $request,
        MerchantLedgerService $ledger,
        WithdrawalPolicyService $policies,
    ) {
        $merchant = $request->user()->merchant()
            ->select(['id', 'user_id', 'verification_status'])
            ->first();
        if (! $merchant) {
            return redirect()->route('merchant.profile.edit')
                ->withErrors(['merchant' => 'أنشئ ملف التاجر أولًا.']);
        }

        return view('merchant.withdrawals.index', [
            'merchant' => $merchant,
            'methods' => $merchant->payoutMethods()
                ->select(['id', 'merchant_id', 'provider_name', 'last_four', 'status'])
                ->latest('id')->get(),
            'verifiedMethods' => $merchant->payoutMethods()
                ->select(['id', 'merchant_id', 'provider_name', 'last_four', 'status'])
                ->where('status', MerchantPayoutMethodStatus::Verified->value)
                ->latest('id')->get(),
            'withdrawals' => $merchant->withdrawalRequests()
                ->select([
                    'id', 'merchant_id', 'reference', 'amount', 'currency', 'status',
                    'destination_snapshot', 'transaction_reference', 'requested_at',
                ])
                ->with(['proof:id,withdrawal_request_id'])
                ->latest('id')->paginate(30),
            'balances' => $ledger->balancesForMerchant($merchant->id),
            'policy' => $policies->policy(),
        ]);
    }

    public function store(
        StoreWithdrawalRequest $request,
        WithdrawalService $withdrawals,
    ) {
        $method = MerchantPayoutMethod::query()->findOrFail(
            (int) $request->validated('merchant_payout_method_id')
        );
        $withdrawal = $withdrawals->request(
            $request->user()->merchant,
            $method,
            $request->validated(),
            $request->user(),
        );

        return back()->with('success', "تم إرسال طلب السحب {$withdrawal->reference} وحجز المبلغ للمراجعة.");
    }
}

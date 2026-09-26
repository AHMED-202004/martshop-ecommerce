<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Services\MerchantLedgerService;
use Illuminate\Http\Request;

class MerchantLedgerController extends Controller
{
    public function index(Request $request, MerchantLedgerService $ledger)
    {
        $merchant = $request->user()->merchant;
        if (! $merchant) {
            return redirect()->route('merchant.profile.edit')
                ->withErrors(['merchant' => 'أنشئ ملف التاجر أولًا.']);
        }

        return view('merchant.ledger.index', [
            'entries' => $merchant->ledgerEntries()
                ->select([
                    'id', 'merchant_id', 'order_id', 'entry_type', 'status', 'gross_amount',
                    'commission_amount', 'net_amount', 'currency', 'created_at',
                ])
                ->latest('id')
                ->paginate(40),
            'balances' => $ledger->balancesForMerchant($merchant->id),
        ]);
    }
}

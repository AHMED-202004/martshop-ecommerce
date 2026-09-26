<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\Merchant;
use App\Services\MerchantLedgerService;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
    public function index(Request $request, MerchantLedgerService $ledger)
    {
        abort_unless($request->user()->hasPermission('ledger.view'), 403);
        $filters = $request->validate([
            'merchant_id' => ['nullable', 'integer', 'exists:merchants,id'],
            'status' => ['nullable', 'in:pending,available,held,withdrawn,refunded'],
        ]);

        $entries = LedgerEntry::query()
            ->select([
                'id', 'merchant_id', 'order_id', 'entry_type', 'status', 'gross_amount',
                'commission_amount', 'net_amount', 'currency', 'reference', 'created_at',
            ])
            ->with('merchant:id,legal_name')
            ->when($filters['merchant_id'] ?? null, fn ($query, $id) => $query->where('merchant_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate(50)
            ->appends(array_filter($filters, fn ($value) => $value !== null && $value !== ''));
        $selectedMerchant = isset($filters['merchant_id'])
            ? Merchant::query()->select(['id', 'legal_name'])->find($filters['merchant_id'])
            : null;

        return view('admin.ledger.index', [
            'entries' => $entries,
            'merchants' => Merchant::query()->orderBy('legal_name')->get(['id', 'legal_name']),
            'selectedMerchant' => $selectedMerchant,
            'balances' => $selectedMerchant ? $ledger->balancesForMerchant($selectedMerchant->id) : null,
        ]);
    }
}

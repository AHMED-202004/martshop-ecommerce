<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveMerchantRequest;
use App\Http\Requests\Admin\MerchantDecisionRequest;
use App\Models\Merchant;
use App\Services\MerchantVerificationService;
use Illuminate\Http\Request;

class MerchantVerificationController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('merchants.view'), 403);

        $merchants = Merchant::query()
            ->select(['id', 'user_id', 'location_id', 'legal_name', 'verification_status', 'submitted_at'])
            ->with(['user:id,name,email', 'location:id,name'])
            ->latest('submitted_at')
            ->paginate(30);

        return view('admin.merchants.index', compact('merchants'));
    }

    public function show(Request $request, Merchant $merchant)
    {
        abort_unless($request->user()->can('view', $merchant), 403);
        $merchant->load(['user', 'location', 'documents.reviewer', 'reviewer']);

        return view('admin.merchants.show', compact('merchant'));
    }

    public function approve(ApproveMerchantRequest $request, Merchant $merchant, MerchantVerificationService $verification)
    {
        $verification->approve($merchant, $request->user());

        return back()->with('success', 'تم اعتماد التاجر.');
    }

    public function requestChanges(MerchantDecisionRequest $request, Merchant $merchant, MerchantVerificationService $verification)
    {
        $verification->requestChanges($merchant, $request->user(), $request->validated('reason'));

        return back()->with('success', 'تم طلب تعديلات من التاجر.');
    }

    public function reject(MerchantDecisionRequest $request, Merchant $merchant, MerchantVerificationService $verification)
    {
        $verification->reject($merchant, $request->user(), $request->validated('reason'));

        return back()->with('success', 'تم رفض طلب التاجر.');
    }

    public function suspend(MerchantDecisionRequest $request, Merchant $merchant, MerchantVerificationService $verification)
    {
        $verification->suspend($merchant, $request->user(), $request->validated('reason'));

        return back()->with('success', 'تم تعليق حساب التاجر.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCommissionRuleRequest;
use App\Http\Requests\Admin\UpdateCommissionRuleRequest;
use App\Models\Category;
use App\Models\CommissionRule;
use App\Models\Merchant;
use App\Services\CommissionRuleService;
use Illuminate\Http\Request;

class CommissionRuleController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('commissions.manage'), 403);

        return view('admin.commissions.index', [
            'rules' => CommissionRule::query()->with(['merchant:id,legal_name', 'category:id,name,path'])
                ->orderByDesc('is_active')->orderByDesc('priority')->orderByDesc('id')->get(),
            'merchants' => Merchant::query()->orderBy('legal_name')->get(['id', 'legal_name']),
            'categories' => Category::query()->active()->orderBy('path')->get(['id', 'name', 'path']),
        ]);
    }

    public function store(StoreCommissionRuleRequest $request, CommissionRuleService $rules)
    {
        $rules->create($request->validated(), $request->user());

        return back()->with('success', 'تمت إضافة قاعدة العمولة.');
    }

    public function update(
        UpdateCommissionRuleRequest $request,
        CommissionRule $commissionRule,
        CommissionRuleService $rules,
    ) {
        $rules->update($commissionRule, $request->validated(), $request->user());

        return back()->with('success', 'تم تحديث قاعدة العمولة دون تغيير الطلبات السابقة.');
    }
}

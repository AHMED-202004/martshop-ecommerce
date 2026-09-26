<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateMarketplaceSettingsRequest;
use App\Services\MarketplaceSettingsManager;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class MarketplaceSettingsController extends Controller
{
    public function edit(Request $request, MarketplaceSettingsManager $settings)
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);

        return view('admin.settings.edit', ['settings' => $settings->values()]);
    }

    public function update(UpdateMarketplaceSettingsRequest $request, MarketplaceSettingsManager $settings)
    {
        $values = Arr::dot($request->safe()->except(['reason', 'current_password']));
        $values['reason'] = $request->validated('reason');
        $settings->update($values, $request->user());

        return back()->with('success', 'تم حفظ الإعدادات التشغيلية وتسجيل التغيير.');
    }
}

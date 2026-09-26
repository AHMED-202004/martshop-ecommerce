<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CloseDeliveryDisputeRequest;
use App\Models\Delivery;
use App\Services\DeliveryDisputeService;
use App\Services\MarketplaceSettings;
use Illuminate\Http\Request;

class SettlementController extends Controller
{
    public function index(Request $request, MarketplaceSettings $settings)
    {
        abort_unless($request->user()->hasPermission('settlements.manage'), 403);

        return view('admin.settlements.index', [
            'deliveries' => Delivery::query()->where('status', 'delivered')
                ->whereHas('order', fn ($query) => $query->where('user_id', '!=', $request->user()->id))
                ->with('dispute.refundRequest')->latest('id')->paginate(30),
            'autoRelease' => $settings->boolean('settlement.auto_release_enabled'),
        ]);
    }

    public function close(CloseDeliveryDisputeRequest $request, Delivery $delivery, DeliveryDisputeService $service)
    {
        $service->close($delivery, $request->user(), $request->validated('reason'));

        return back()->with('success', 'أُغلق النزاع وعادت المستحقات إلى المعلّقة، مع الالتزام بموعد التحرير الأصلي.');
    }
}

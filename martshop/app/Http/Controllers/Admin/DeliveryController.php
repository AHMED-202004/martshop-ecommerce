<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Enums\MerchantOrderStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignDeliveryRequest;
use App\Http\Requests\Admin\PromoteDeliveryWorkerRequest;
use App\Models\MerchantOrder;
use App\Models\User;
use App\Services\DeliveryAssignmentService;
use App\Services\DeliveryWorkerService;
use App\Services\DeliveryOutcomeService;
use App\Http\Requests\Admin\RecordDeliveryOutcomeRequest;
use App\Http\Requests\Admin\SaveDeliverySlaRuleRequest;
use App\Models\DeliverySlaRule;
use App\Models\Location;
use App\Services\DeliverySlaRuleService;
use App\Http\Requests\RecordDeliveryDelayRequest;
use App\Services\DeliveryDelayService;
use Illuminate\Http\Request;
use App\Enums\DeliveryStatus;
use App\Enums\DeliveryWorkerAvailability;
use App\Http\Requests\Admin\UnassignDeliveryRequest;
use App\Http\Requests\UpdateDeliveryWorkerAvailabilityRequest;
use App\Models\Delivery;
use App\Services\DeliveryWorkerAvailabilityService;

class DeliveryController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('deliveries.manage'), 403);

        return view('admin.deliveries.index', [
            'merchantOrders' => MerchantOrder::query()
                ->with([
                    'order:id,status,payment_status,delivery_address_snapshot',
                    'merchant:id,legal_name,phone,address',
                    'delivery.worker:id,name,email', 'delivery.proof:id,delivery_id',
                ])
                ->whereNotNull('merchant_id')
                ->where('status', MerchantOrderStatus::Confirmed->value)
                ->whereHas('order', fn ($query) => $query
                    ->where('status', OrderStatus::Confirmed->value)
                    ->where('payment_status', OrderPaymentStatus::Paid->value))
                ->latest('id')->paginate(40),
            'workers' => User::query()
                ->where('account_status', AccountStatus::Active->value)
                ->whereHas('roles', fn ($query) => $query->where('slug', 'delivery-worker'))
                ->with(['staffLocations:id', 'deliveryWorkerProfile'])
                ->withCount([
                    'deliveryAssignments as current_active_count' => fn ($query) => $query->whereIn('status', [DeliveryStatus::Accepted->value, DeliveryStatus::PickedUp->value, DeliveryStatus::InTransit->value]),
                    'deliveryAssignments as queued_count' => fn ($query) => $query->where('status', DeliveryStatus::Assigned->value),
                    'deliveryAssignments as completed_today_count' => fn ($query) => $query->where('status', DeliveryStatus::Delivered->value)->whereDate('delivered_at', today()),
                ])
                ->orderBy('name')->get(['id', 'name', 'email']),
            'slaRules' => DeliverySlaRule::query()->with('originLocation:id,name')->orderByDesc('priority')->orderBy('id')->get(),
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function promote(
        PromoteDeliveryWorkerRequest $request,
        DeliveryWorkerService $workers,
    ) {
        $workers->promoteByEmail($request->validated('email'), $request->user());

        return back()->with('success', 'تم تفعيل حساب عامل التوصيل.');
    }

    public function assign(
        AssignDeliveryRequest $request,
        MerchantOrder $merchantOrder,
        DeliveryAssignmentService $deliveries,
    ) {
        $worker = User::query()->findOrFail((int) $request->validated('delivery_worker_id'));
        $delivery = $deliveries->assign(
            $merchantOrder,
            $worker,
            $request->user(),
            $request->validated('assignment_notes'),
            (int) $request->validated('lock_version'),
            $request->validated('expected_delivery_at'),
            $request->validated('order_type', 'standard'),
            $request->validated('delivery_method', 'courier'),
            $request->validated('distance_km') !== null ? (float) $request->validated('distance_km') : null,
        );

        return back()->with('success', "تم إسناد المهمة {$delivery->reference}.");
    }

    public function outcome(RecordDeliveryOutcomeRequest $request, \App\Models\Delivery $delivery, DeliveryOutcomeService $outcomes)
    {
        $outcomes->record($delivery, $request->user(), \App\Enums\DeliveryStatus::from($request->validated('status')), $request->validated('reason'), (int) $request->validated('lock_version'));
        return back()->with('success', 'تم تسجيل نتيجة مهمة التوصيل.');
    }

    public function storeSlaRule(SaveDeliverySlaRuleRequest $request, DeliverySlaRuleService $rules)
    {
        $rules->save(null, $request->validated(), $request->user());
        return back()->with('success', 'تم إنشاء قاعدة SLA للتوصيل.');
    }

    public function updateSlaRule(SaveDeliverySlaRuleRequest $request, DeliverySlaRule $deliverySlaRule, DeliverySlaRuleService $rules)
    {
        $rules->save($deliverySlaRule, $request->validated(), $request->user());
        return back()->with('success', 'تم تحديث قاعدة SLA للتوصيل.');
    }

    public function delay(RecordDeliveryDelayRequest $request, \App\Models\Delivery $delivery, DeliveryDelayService $service)
    {
        $service->record($delivery, $request->user(), $request->validated('reason'), $request->validated('responsibility'), $request->validated('note'), (int) $request->validated('lock_version'));
        return back()->with('success', 'تم تسجيل سبب التأخير والجهة المسؤولة.');
    }

    public function unassign(UnassignDeliveryRequest $request, Delivery $delivery, DeliveryAssignmentService $service)
    {
        $service->unassign($delivery, $request->user(), $request->validated('reason'), (int) $request->validated('lock_version'));
        return back()->with('success', 'تم إلغاء الإسناد مع الاحتفاظ بسجل المهمة.');
    }

    public function workerAvailability(UpdateDeliveryWorkerAvailabilityRequest $request, User $worker, DeliveryWorkerAvailabilityService $service)
    {
        $service->update($worker, $request->user(), DeliveryWorkerAvailability::from($request->validated('availability')), $request->validated('reason'));
        return back()->with('success', 'تم تحديث حالة عامل التوصيل.');
    }
}

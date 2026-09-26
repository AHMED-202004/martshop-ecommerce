<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Http\Requests\Delivery\AcceptDeliveryRequest;
use App\Http\Requests\Delivery\ConfirmDeliveryRequest;
use App\Http\Requests\Delivery\UpdateDeliveryStatusRequest;
use App\Models\Delivery;
use App\Services\DeliveryAssignmentService;
use App\Services\DeliveryConfirmationService;
use Illuminate\Http\Request;
use App\Http\Requests\RecordDeliveryDelayRequest;
use App\Services\DeliveryDelayService;
use App\Enums\DeliveryWorkerAvailability;
use App\Http\Requests\UpdateDeliveryWorkerAvailabilityRequest;
use App\Services\DeliveryWorkerAvailabilityService;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('deliveries.view-own'), 403);

        return view('delivery.tasks.index', [
            'workerProfile' => $request->user()->deliveryWorkerProfile,
            'deliveries' => $request->user()->deliveryAssignments()
                ->with([
                    'merchantOrder.items:id,merchant_order_id,product_name,qty',
                    'events:id,delivery_id,event_type,created_at',
                    'proof:id,delivery_id',
                ])
                ->latest('assigned_at')->paginate(30),
        ]);
    }

    public function availability(UpdateDeliveryWorkerAvailabilityRequest $request, DeliveryWorkerAvailabilityService $service)
    {
        abort_unless($request->user()->hasRole('delivery-worker'), 403);
        $service->update($request->user(), $request->user(), DeliveryWorkerAvailability::from($request->validated('availability')), $request->validated('reason'));
        return back()->with('success', 'تم تحديث حالة توفرك.');
    }

    public function accept(
        AcceptDeliveryRequest $request,
        Delivery $delivery,
        DeliveryAssignmentService $deliveries,
    ) {
        $deliveries->accept(
            $delivery,
            $request->user(),
            (int) $request->validated('lock_version'),
        );

        return back()->with('success', 'تم قبول مهمة التوصيل.');
    }

    public function pickedUp(UpdateDeliveryStatusRequest $request, Delivery $delivery, DeliveryConfirmationService $service)
    {
        $service->markPickedUp($delivery, $request->user(), (int) $request->validated('lock_version'), $request->validated('note'));

        return back()->with('success', 'تم تسجيل استلام الطرد من التاجر.');
    }

    public function milestone(UpdateDeliveryStatusRequest $request, Delivery $delivery, string $milestone, DeliveryConfirmationService $service)
    {
        $service->recordMilestone($delivery, $request->user(), (int) $request->validated('lock_version'), $milestone, $request->validated('note'));
        return back()->with('success', 'تم تسجيل مرحلة التوصيل.');
    }

    public function delay(RecordDeliveryDelayRequest $request, Delivery $delivery, DeliveryDelayService $service)
    {
        $service->record($delivery, $request->user(), $request->validated('reason'), $request->validated('responsibility'), $request->validated('note'), (int) $request->validated('lock_version'));
        return back()->with('success', 'تم تسجيل سبب التأخير والجهة المسؤولة.');
    }

    public function inTransit(UpdateDeliveryStatusRequest $request, Delivery $delivery, DeliveryConfirmationService $service)
    {
        $service->markInTransit($delivery, $request->user(), (int) $request->validated('lock_version'), $request->validated('note'));

        return back()->with('success', 'تم تحديث المهمة إلى في الطريق.');
    }

    public function delivered(ConfirmDeliveryRequest $request, Delivery $delivery, DeliveryConfirmationService $service)
    {
        $service->confirmDelivered(
            $delivery,
            $request->user(),
            (int) $request->validated('lock_version'),
            $request->validated('pin'),
            $request->file('proof'),
            $request->validated('note'),
        );

        return back()->with('success', 'تم تأكيد التسليم؛ تبقى مستحقات التاجر معلّقة لحين انتهاء مهلة النزاع.');
    }
}

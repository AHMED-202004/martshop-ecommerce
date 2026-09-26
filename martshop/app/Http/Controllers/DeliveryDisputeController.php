<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeliveryDisputeRequest;
use App\Models\Delivery;
use App\Services\DeliveryDisputeService;

class DeliveryDisputeController extends Controller
{
    public function store(StoreDeliveryDisputeRequest $request, Delivery $delivery, DeliveryDisputeService $service)
    {
        $service->open($delivery, $request->user(), $request->validated('reason'));

        return back()->with('success', 'تم فتح النزاع وحجز مستحقات هذا الطلب.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeliveryRatingRequest;
use App\Models\Delivery;
use App\Services\DeliveryRatingService;

class DeliveryRatingController extends Controller
{
    public function store(StoreDeliveryRatingRequest $request, Delivery $delivery, DeliveryRatingService $service)
    {
        $service->create($delivery, $request->user(), (int) $request->validated('rating'), $request->validated('comment'));
        return back()->with('success', 'شكرًا، تم تسجيل تقييم تجربة التوصيل.');
    }
}

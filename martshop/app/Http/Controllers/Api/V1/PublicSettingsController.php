<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\MarketplaceSettings;

class PublicSettingsController extends Controller
{
    public function __invoke(MarketplaceSettings $settings)
    {
        return response()->json(['data' => [
            'name' => $settings->string('site.name'),
            'contact_phone' => $settings->string('site.contact_phone'),
            'email' => $settings->string('site.email'),
            'support_hours' => $settings->string('site.support_hours'),
            'whatsapp' => $settings->string('site.whatsapp'),
            'emergency_notice' => $settings->string('site.emergency_notice'),
            'delivery_text' => $settings->deliveryText(),
            'orders_enabled' => $settings->boolean('site.orders_enabled'),
            'registration_enabled' => $settings->boolean('site.registration_enabled'),
            'merchant_registration_enabled' => $settings->boolean('site.merchant_registration_enabled'),
            'chat_enabled' => $settings->boolean('site.chat_enabled'),
        ]]);
    }
}

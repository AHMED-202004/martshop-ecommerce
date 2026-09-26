<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMarketplaceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('settings.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $site = $this->input('site', []);
        if (! is_array($site)) {
            return;
        }
        foreach (['chat_enabled', 'registration_enabled', 'merchant_registration_enabled', 'orders_enabled'] as $key) {
            // Only an omitted checkbox means false; invalid submitted values must fail validation.
            if (! array_key_exists($key, $site)) {
                $site[$key] = false;
            }
        }
        $this->merge(['site' => $site]);
        if (is_array($this->input('settlement'))) {
            $settlement = $this->input('settlement');
            if (! array_key_exists('auto_release_enabled', $settlement)) {
                $settlement['auto_release_enabled'] = false;
            }
            $this->merge(['settlement' => $settlement]);
        }
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password:web'],
            'settlement' => ['sometimes', 'array'],
            'settlement.dispute_days' => ['required_with:settlement', 'integer', 'between:1,90'],
            'settlement.auto_release_enabled' => ['required_with:settlement', 'boolean'],
            'support' => ['sometimes', 'array'],
            'support.first_response_sla_minutes' => ['required_with:support', 'integer', 'between:5,10080'],
            'site' => ['required', 'array'],
            'checkout' => ['required', 'array'],
            'checkout.shipping' => ['required', 'array'],
            'site.name' => ['required', 'string', 'min:2', 'max:100'],
            'site.contact_phone' => ['nullable', 'string', 'max:30'],
            'site.whatsapp' => ['nullable', 'string', 'max:30'],
            'site.email' => ['nullable', 'email', 'max:191'],
            'site.support_hours' => ['nullable', 'string', 'max:200'],
            'site.emergency_notice' => ['nullable', 'string', 'max:500'],
            'site.chat_enabled' => ['required', 'boolean'],
            'site.registration_enabled' => ['required', 'boolean'],
            'site.merchant_registration_enabled' => ['required', 'boolean'],
            'site.orders_enabled' => ['required', 'boolean'],
            'site.default_delivery_text' => ['nullable', 'string', 'max:500'],
            'checkout.shipping.flat_fee' => ['required', 'numeric', 'min:0', 'max:10000'],
            'checkout.shipping.free_threshold' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'checkout.reservation_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}

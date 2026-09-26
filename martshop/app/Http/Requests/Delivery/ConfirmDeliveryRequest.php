<?php

namespace App\Http\Requests\Delivery;

use App\Models\Delivery;
use App\Services\DeliveryConfirmationService;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $delivery = $this->route('delivery');

        return $delivery instanceof Delivery && ($this->user()?->can('confirm', $delivery) ?? false);
    }

    public function rules(): array
    {
        return DeliveryConfirmationService::confirmationRules();
    }
}

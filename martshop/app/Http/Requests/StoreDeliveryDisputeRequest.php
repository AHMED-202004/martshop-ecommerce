<?php

namespace App\Http\Requests;

use App\Models\Delivery;
use Illuminate\Foundation\Http\FormRequest;

class StoreDeliveryDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $delivery = $this->route('delivery');

        return $delivery instanceof Delivery && $this->user()
            && ($this->user()->hasPermission('settlements.manage')
                || $delivery->order->user_id === $this->user()->id);
    }

    public function rules(): array
    {
        $rules = ['reason' => ['required', 'string', 'min:5', 'max:2000']];
        $delivery = $this->route('delivery');
        $isAdministrativeAction = $delivery instanceof Delivery
            && $this->user()?->hasPermission('settlements.manage')
            && $delivery->order->user_id !== $this->user()->id;

        if ($isAdministrativeAction) {
            $rules['current_password'] = ['required', 'current_password:web'];
        }

        return $rules;
    }
}

<?php

namespace App\Http\Requests\Delivery;

use App\Models\Delivery;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDeliveryStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $delivery = $this->route('delivery');

        return $delivery instanceof Delivery && ($this->user()?->can('updateStatus', $delivery) ?? false);
    }

    public function rules(): array
    {
        return [
            'lock_version' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

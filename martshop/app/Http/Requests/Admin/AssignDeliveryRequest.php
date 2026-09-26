<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AssignDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('deliveries.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'delivery_worker_id' => ['required', 'integer', 'exists:users,id'],
            'assignment_notes' => ['nullable', 'string', 'max:1000'],
            'expected_delivery_at' => ['nullable', 'date', 'after:now'],
            'order_type' => ['nullable', 'string', 'in:standard,express,scheduled'],
            'delivery_method' => ['nullable', 'string', 'in:courier,pickup_point,third_party'],
            'distance_km' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'lock_version' => ['required', 'integer', 'min:0'],
        ];
    }
}

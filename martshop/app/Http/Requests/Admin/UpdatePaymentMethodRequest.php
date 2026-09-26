<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('payment-methods.manage') ?? false;
    }

    public function rules(): array
    {
        $method = $this->route('paymentMethod');

        return [
            'current_password' => ['required', 'current_password:web'],
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'alpha_dash', 'max:100', Rule::unique('payment_methods', 'slug')->ignore($method)],
            'account_name' => ['nullable', 'required_if:is_active,1', 'string', 'max:150'],
            'account_identifier' => ['nullable', 'required_if:is_active,1', 'string', 'max:200'],
            'instructions' => ['nullable', 'required_if:is_active,1', 'string', 'max:3000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}

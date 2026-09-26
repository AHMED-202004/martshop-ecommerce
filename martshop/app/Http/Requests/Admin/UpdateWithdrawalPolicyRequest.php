<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWithdrawalPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('withdrawals.settings') ?? false;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password:web'],
            'enabled' => ['nullable', 'boolean'],
            'minimum_amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999999.99'],
            'maximum_amount' => ['required', 'numeric', 'decimal:0,2', 'gte:minimum_amount', 'max:999999999.99'],
            'daily_limit' => ['required', 'numeric', 'decimal:0,2', 'gte:maximum_amount', 'max:999999999.99'],
            'weekly_limit' => ['required', 'numeric', 'decimal:0,2', 'gte:daily_limit', 'max:999999999.99'],
        ];
    }
}

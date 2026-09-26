<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;

class StoreWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->merchant !== null;
    }

    public function rules(): array
    {
        return [
            'merchant_payout_method_id' => ['required', 'integer', 'exists:merchant_payout_methods,id'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999999.99'],
            'idempotency_key' => ['required', 'uuid'],
            'current_password' => ['required', 'current_password'],
        ];
    }
}

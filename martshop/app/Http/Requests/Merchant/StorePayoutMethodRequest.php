<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayoutMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->merchant !== null;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['bank_account', 'mobile_wallet'])],
            'provider_name' => ['required', 'string', 'min:2', 'max:100'],
            'account_name' => ['required', 'string', 'min:2', 'max:150'],
            'account_identifier' => ['required', 'string', 'min:4', 'max:200'],
            'current_password' => ['required', 'current_password'],
        ];
    }
}

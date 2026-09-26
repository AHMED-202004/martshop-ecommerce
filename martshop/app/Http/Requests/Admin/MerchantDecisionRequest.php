<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class MerchantDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $merchant = $this->route('merchant');

        return $merchant && $this->user()?->can('verify', $merchant);
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password:web'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}

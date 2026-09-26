<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;

class RejectMerchantOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $merchantOrder = $this->route('merchantOrder');

        return $merchantOrder && $this->user()?->can('reject', $merchantOrder) === true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password:web'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}

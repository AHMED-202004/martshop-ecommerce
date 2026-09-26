<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmMerchantOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $merchantOrder = $this->route('merchantOrder');

        return $merchantOrder && $this->user()?->can('confirm', $merchantOrder) === true;
    }

    public function rules(): array
    {
        return ['current_password' => ['required', 'current_password:web']];
    }
}

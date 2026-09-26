<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;

class SubmitMerchantProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $merchant = $this->user()?->merchant()->first();

        return $merchant !== null && $this->user()->can('submit', $merchant);
    }

    public function rules(): array
    {
        return ['current_password' => ['required', 'current_password:web']];
    }
}

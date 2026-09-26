<?php

namespace App\Http\Requests\Merchant;

use App\Models\MerchantPayoutMethod;
use Illuminate\Foundation\Http\FormRequest;

class DisablePayoutMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        $method = $this->route('payoutMethod');

        return $method instanceof MerchantPayoutMethod
            && $method->merchant?->user_id === $this->user()?->id;
    }

    public function rules(): array
    {
        return ['current_password' => ['required', 'current_password']];
    }
}

<?php

namespace App\Http\Requests\Admin;

use App\Enums\MerchantPayoutMethodStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewPayoutMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('withdrawals.approve') ?? false;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password:web'],
            'decision' => ['required', Rule::in([
                MerchantPayoutMethodStatus::Verified->value,
                MerchantPayoutMethodStatus::Rejected->value,
            ])],
            'notes' => [
                Rule::requiredIf($this->input('decision') === MerchantPayoutMethodStatus::Rejected->value),
                'nullable', 'string', 'min:5', 'max:2000',
            ],
            'lock_version' => ['required', 'integer', 'min:0'],
        ];
    }
}

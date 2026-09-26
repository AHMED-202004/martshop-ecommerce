<?php

namespace App\Http\Requests\Admin;

use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('payments.verify') ?? false;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password:web'],
            'decision' => [
                'required',
                Rule::in([
                    PaymentStatus::Accepted->value,
                    PaymentStatus::Rejected->value,
                    PaymentStatus::ShortAmount->value,
                    PaymentStatus::Overpaid->value,
                    PaymentStatus::Duplicate->value,
                    PaymentStatus::Suspicious->value,
                ]),
            ],
            'reason' => ['nullable', 'required_unless:decision,accepted', 'string', 'min:5', 'max:2000'],
            'lock_version' => ['required', 'integer', 'min:0'],
        ];
    }
}

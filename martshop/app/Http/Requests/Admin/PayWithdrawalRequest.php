<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('withdrawals.approve') ?? false;
    }

    public function rules(): array
    {
        $withdrawal = $this->route('withdrawalRequest');

        return [
            'current_password' => ['required', 'current_password:web'],
            'transaction_reference' => [
                'required', 'string', 'min:3', 'max:150',
                Rule::unique('withdrawal_requests', 'transaction_reference')->ignore($withdrawal),
            ],
            'transferred_at' => ['required', 'date', 'before_or_equal:now'],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'lock_version' => ['required', 'integer', 'min:0'],
        ];
    }
}

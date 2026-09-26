<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PrepareRefundTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('refunds.pay') ?? false;
    }

    public function rules(): array
    {
        return ['current_password' => ['required', 'current_password:web'],
            'lock_version' => ['required', 'integer', 'min:0'],
            'destination_id' => ['required', 'integer', 'min:1'],
            'destination_version' => ['required', 'integer', 'min:0']];
    }
}

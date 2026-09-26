<?php

namespace App\Http\Requests\Admin;

use App\Services\RefundTransferService;
use Illuminate\Foundation\Http\FormRequest;

class CancelRefundTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('refunds.pay') && $this->user()->hasPermission('refunds.cancel');
    }

    public function rules(): array
    {
        return ['current_password' => ['required', 'current_password:web']] + RefundTransferService::cancellationRules();
    }
}

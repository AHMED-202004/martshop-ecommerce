<?php

namespace App\Http\Requests\Admin;

class RecordRefundTransferRequest extends PrepareRefundTransferRequest
{
    public function rules(): array
    {
        return ['current_password' => ['required', 'current_password:web']]
            + \App\Services\RefundTransferService::receiptRules();
    }
}

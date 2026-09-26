<?php

namespace App\Http\Requests;

class StoreRefundDestinationRequest extends ChangeRefundDestinationRequest
{
    public function rules(): array
    {
        return parent::rules() + \App\Services\RefundDestinationService::recipientRules();
    }
}

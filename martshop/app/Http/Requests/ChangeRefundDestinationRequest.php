<?php

namespace App\Http\Requests;

use App\Models\RefundRequest;
use Illuminate\Foundation\Http\FormRequest;

class ChangeRefundDestinationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $refundId = $this->route('refundRequest')?->id
            ?? $this->route('refundDestination')?->refund_request_id;

        return $this->user() && $refundId && RefundRequest::query()->whereKey($refundId)
            ->whereHas('dispute.delivery.order', fn ($query) => $query->where('user_id', $this->user()->id))
            ->exists();
    }

    public function rules(): array
    {
        return ['current_password' => ['required', 'current_password:web'], 'lock_version' => ['required', 'integer', 'min:0']];
    }
}

<?php

namespace App\Http\Requests;

use App\Services\PaymentSubmissionService;
use Illuminate\Foundation\Http\FormRequest;

class SubmitManualPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->id === $this->route('order')?->user_id;
    }

    public function rules(): array
    {
        return PaymentSubmissionService::submissionRules();
    }
}

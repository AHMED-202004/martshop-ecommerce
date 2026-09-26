<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReviewRefundDestinationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('refund-destinations.review') ?? false;
    }

    public function rules(): array
    {
        return ['decision' => ['required', 'in:verified,rejected'],
            'review_notes' => ['required', 'string', 'min:5', 'max:2000'],
            'ownership_confirmed' => ['accepted_if:decision,verified'],
            'lock_version' => ['required', 'integer', 'min:0'],
            'current_password' => ['required', 'current_password:web']];
    }
}

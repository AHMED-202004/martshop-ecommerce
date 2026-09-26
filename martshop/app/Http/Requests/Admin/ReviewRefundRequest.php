<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReviewRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('refunds.review') ?? false;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:approved,rejected'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'lock_version' => ['required', 'integer', 'min:0'],
            'current_password' => ['required', 'current_password:web'],
        ];
    }
}

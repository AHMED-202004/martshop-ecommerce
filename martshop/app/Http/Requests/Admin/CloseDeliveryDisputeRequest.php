<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CloseDeliveryDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('settlements.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'current_password' => ['required', 'current_password:web'],
        ];
    }
}

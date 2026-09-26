<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('contact-messages.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'assigned_to' => ['required', 'integer', Rule::exists('users', 'id')],
            'expected_version' => ['required', 'integer', 'min:0'],
        ];
    }
}

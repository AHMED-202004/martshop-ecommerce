<?php

namespace App\Http\Requests\Admin;

use App\Enums\SupportTicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('contact-messages.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(SupportTicketStatus::class)],
            'expected_version' => ['required', 'integer', 'min:0'],
        ];
    }
}

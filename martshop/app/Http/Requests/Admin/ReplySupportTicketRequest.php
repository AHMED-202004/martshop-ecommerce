<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReplySupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('contact-messages.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_internal' => $this->boolean('is_internal')]);
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:2', 'max:4000'],
            'is_internal' => ['required', 'boolean'],
            'expected_version' => ['required', 'integer', 'min:0'],
        ];
    }
}

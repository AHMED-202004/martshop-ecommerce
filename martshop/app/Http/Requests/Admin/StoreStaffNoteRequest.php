<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreStaffNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('staff-notes.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:3', 'max:2000'],
            'current_password' => ['required', 'current_password:web'],
        ];
    }
}

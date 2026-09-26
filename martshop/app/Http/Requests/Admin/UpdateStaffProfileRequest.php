<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffProfileRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['name', 'phone', 'job_title'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field)) ?: null]);
            }
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('roles.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30', Rule::unique('users', 'phone')->ignore($this->route('staff'))],
            'job_title' => ['nullable', 'string', 'max:120'],
            'staff_department_id' => ['nullable', 'integer', Rule::exists('staff_departments', 'id')->where('is_active', true)],
            'current_password' => ['required', 'current_password:web'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}

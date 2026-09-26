<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffInvitationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
        if (! $this->has('permission_ids')) {
            $this->merge(['permission_ids' => []]);
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
            'email' => ['required', 'string', 'email', 'max:191', Rule::unique('users', 'email')],
            'job_title' => ['nullable', 'string', 'max:120'],
            'staff_department_id' => ['nullable', 'integer', Rule::exists('staff_departments', 'id')->where('is_active', true)],
            'role_ids' => ['required', 'array', 'min:1', 'max:20'],
            'role_ids.*' => ['required', 'integer', 'distinct', Rule::exists('roles', 'id')],
            'permission_ids' => ['array', 'max:100'],
            'permission_ids.*' => ['required', 'integer', 'distinct', Rule::exists('permissions', 'id')],
            'current_password' => ['required', 'current_password:web'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}

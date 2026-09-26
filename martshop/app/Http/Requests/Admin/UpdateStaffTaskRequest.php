<?php

namespace App\Http\Requests\Admin;

use App\Enums\StaffTaskPriority;
use App\Enums\StaffTaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('roles.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'assigned_to' => ['required', 'integer', Rule::exists('users', 'id')],
            'priority' => ['required', Rule::enum(StaffTaskPriority::class)],
            'due_at' => ['nullable', 'date'],
            'status' => ['required', Rule::enum(StaffTaskStatus::class)],
            'expected_version' => ['required', 'integer', 'min:0'],
            'current_password' => ['required', 'current_password:web'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use App\Enums\StaffTaskPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('roles.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'task_type' => ['required', Rule::in(['general', 'order', 'merchant', 'product', 'payment', 'withdrawal', 'complaint', 'delivery', 'support'])],
            'related_id' => ['nullable', 'integer', 'min:1'],
            'priority' => ['required', Rule::enum(StaffTaskPriority::class)],
            'due_at' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'current_password' => ['required', 'current_password:web'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}

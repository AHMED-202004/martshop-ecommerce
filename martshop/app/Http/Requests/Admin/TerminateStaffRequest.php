<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TerminateStaffRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('confirmation'))) {
            $this->merge(['confirmation' => strtolower(trim($this->input('confirmation')))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('roles.manage')
            && (string) $this->user()->getKey() !== (string) $this->route('staff');
    }

    public function rules(): array
    {
        return [
            'confirmation' => ['required', 'string', 'email', 'max:191'],
            'current_password' => ['required', 'current_password:web'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'reassign_tasks_to' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
                Rule::notIn([(int) $this->route('staff')]),
            ],
            'tasks_destination' => ['nullable', Rule::in(['specific', 'department', 'unassigned'])],
            'chats_destination' => ['nullable', Rule::in(['specific', 'department', 'unassigned'])],
            'deliveries_destination' => ['nullable', Rule::in(['specific', 'department', 'unassigned'])],
            'reassign_chats_to' => ['nullable', 'integer', Rule::exists('users', 'id'), Rule::notIn([(int) $this->route('staff')])],
            'reassign_deliveries_to' => ['nullable', 'integer', Rule::exists('users', 'id'), Rule::notIn([(int) $this->route('staff')])],
        ];
    }
}

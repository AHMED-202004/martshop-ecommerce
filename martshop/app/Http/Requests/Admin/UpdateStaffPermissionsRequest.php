<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffPermissionsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('permission_ids')) {
            $this->merge(['permission_ids' => []]);
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
            'permission_ids' => ['array', 'max:100'],
            'permission_ids.*' => ['required', 'integer', 'distinct', Rule::exists('permissions', 'id')],
            'current_password' => ['required', 'current_password:web'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}

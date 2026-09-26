<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('roles.manage')
            && (string) $this->user()->getKey() !== (string) $this->route('staff');
    }

    public function rules(): array
    {
        return [
            'role_ids' => ['required', 'array', 'min:1', 'max:20'],
            'role_ids.*' => ['required', 'integer', 'distinct', Rule::exists('roles', 'id')],
            'current_password' => ['required', 'current_password:web'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}

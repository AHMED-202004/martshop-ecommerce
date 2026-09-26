<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateStaffRolesRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->hasPermission('roles.manage') === true; }

    public function rules(): array
    {
        return [
            'staff_ids' => ['required', 'array', 'min:2', 'max:50'],
            'staff_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')],
            'role_ids' => ['required', 'array', 'min:1', 'max:10'],
            'role_ids.*' => ['integer', 'distinct', Rule::exists('roles', 'id')],
            'confirmation' => ['required', Rule::in(['BULK ROLE CHANGE'])],
            'current_password' => ['required', 'current_password:web'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }
}

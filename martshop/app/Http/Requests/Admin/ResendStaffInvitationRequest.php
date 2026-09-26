<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ResendStaffInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('roles.manage')
            && (string) $this->user()->getKey() !== (string) $this->route('staff');
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password:web'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}

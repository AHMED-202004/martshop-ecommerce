<?php

namespace App\Http\Requests\Admin;

use App\Enums\AccountStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeStaffStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('roles.manage')
            && (string) $this->user()->getKey() !== (string) $this->route('staff');
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(AccountStatus::class)->only([
                AccountStatus::Active,
                AccountStatus::OnLeave,
                AccountStatus::Suspended,
            ])],
            'current_password' => ['required', 'current_password:web'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffScopeScheduleRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('location_ids')) {
            $this->merge(['location_ids' => []]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('roles.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'location_ids' => ['array', 'max:100'],
            'location_ids.*' => ['integer', 'distinct', Rule::exists('locations', 'id')->where('is_active', true)],
            'schedule' => ['required', 'array', 'size:7'],
            'schedule.*.day_of_week' => ['required', 'integer', 'distinct', 'between:0,6'],
            'schedule.*.is_working' => ['required', 'boolean'],
            'schedule.*.starts_at' => ['nullable', 'date_format:H:i'],
            'schedule.*.ends_at' => ['nullable', 'date_format:H:i'],
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
            'current_password' => ['required', 'current_password:web'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}

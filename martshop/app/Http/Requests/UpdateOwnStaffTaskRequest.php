<?php

namespace App\Http\Requests;

use App\Enums\StaffTaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOwnStaffTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('staff-tasks.update-own') ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(StaffTaskStatus::class)->only([
                StaffTaskStatus::InProgress,
                StaffTaskStatus::Waiting,
                StaffTaskStatus::Completed,
            ])],
            'expected_version' => ['required', 'integer', 'min:0'],
            'progress_note' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}

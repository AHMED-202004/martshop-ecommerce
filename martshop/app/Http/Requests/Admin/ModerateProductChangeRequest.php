<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductChangeRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModerateProductChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $changeRequest = $this->route('changeRequest');

        return $changeRequest && $this->user()?->can('moderate', $changeRequest) === true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password:web'],
            'decision' => ['required', Rule::in([
                ProductChangeRequestStatus::Approved->value,
                ProductChangeRequestStatus::ChangesRequested->value,
                ProductChangeRequestStatus::Rejected->value,
            ])],
            'reason' => [
                Rule::requiredIf($this->input('decision') !== ProductChangeRequestStatus::Approved->value),
                'nullable', 'string', 'max:2000',
            ],
        ];
    }
}

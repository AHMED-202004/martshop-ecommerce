<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductOfferStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModerateProductOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        $offer = $this->route('offer');

        return $offer && $this->user()?->can('moderate', $offer) === true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password:web'],
            'decision' => ['required', Rule::in([
                ProductOfferStatus::Active->value,
                ProductOfferStatus::ChangesRequested->value,
                ProductOfferStatus::Rejected->value,
                ProductOfferStatus::Paused->value,
            ])],
            'reason' => [
                Rule::requiredIf($this->input('decision') !== ProductOfferStatus::Active->value),
                'nullable', 'string', 'max:2000',
            ],
        ];
    }
}

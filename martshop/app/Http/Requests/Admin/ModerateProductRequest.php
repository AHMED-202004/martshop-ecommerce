<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModerateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product && $this->user()?->can('moderate', $product) === true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password:web'],
            'decision' => ['required', Rule::in([
                ProductStatus::Active->value,
                ProductStatus::ChangesRequested->value,
                ProductStatus::Rejected->value,
                ProductStatus::Hidden->value,
            ])],
            'reason' => [
                Rule::requiredIf($this->input('decision') !== ProductStatus::Active->value),
                'nullable', 'string', 'max:2000',
            ],
        ];
    }
}

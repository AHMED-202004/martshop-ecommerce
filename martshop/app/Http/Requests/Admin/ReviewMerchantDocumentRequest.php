<?php

namespace App\Http\Requests\Admin;

use App\Enums\MerchantDocumentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewMerchantDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('merchantDocument');

        return $document && $this->user()?->can('review', $document);
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password:web'],
            'status' => ['required', Rule::in([
                MerchantDocumentStatus::Accepted->value,
                MerchantDocumentStatus::Rejected->value,
            ])],
            'review_notes' => [
                Rule::requiredIf($this->input('status') === MerchantDocumentStatus::Rejected->value),
                'nullable', 'string', 'max:2000',
            ],
        ];
    }
}

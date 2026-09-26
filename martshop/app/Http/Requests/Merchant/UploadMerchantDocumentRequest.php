<?php

namespace App\Http\Requests\Merchant;

use App\Enums\MerchantDocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadMerchantDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $merchant = $this->user()?->merchant()->first();

        return $merchant !== null && $this->user()->can('update', $merchant);
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password:web'],
            'type' => ['required', Rule::enum(MerchantDocumentType::class)],
            'document' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ];
    }
}

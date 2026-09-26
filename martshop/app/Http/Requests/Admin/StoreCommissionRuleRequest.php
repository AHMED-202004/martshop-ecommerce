<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommissionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('commissions.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password:web'],
            'name' => ['required', 'string', 'max:150'],
            'merchant_id' => ['nullable', 'integer', 'exists:merchants,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'percentage' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'priority' => ['nullable', 'integer', 'min:-10000', 'max:10000'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ];
    }
}

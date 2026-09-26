<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('offer')) === true;
    }

    public function rules(): array
    {
        return [
            'lock_version' => ['required', 'integer', 'min:0'],
            'price' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'compare_at_price' => ['nullable', 'numeric', 'gt:price', 'max:99999999.99'],
            'stock' => ['required', 'integer', 'min:0', 'max:100000000'],
            'location_id' => [
                'required',
                Rule::exists('locations', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'preparation_time_days' => ['required', 'integer', 'min:0', 'max:365'],
            'warranty' => ['nullable', 'string', 'max:2000'],
            'variant_sizes' => ['nullable', 'string', 'max:2000'],
            'variant_color' => ['nullable', 'string', 'max:100'],
        ];
    }
}

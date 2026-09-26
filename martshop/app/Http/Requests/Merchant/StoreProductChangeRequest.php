<?php

namespace App\Http\Requests\Merchant;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('proposeChanges', $this->route('product')) === true;
    }

    public function rules(): array
    {
        return [
            'change_request_lock_version' => ['nullable', 'integer', 'min:0'],
            'name' => ['required', 'string', 'max:250'],
            'category_id' => [
                'required',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->where('status', 'active')),
            ],
            'brand_id' => ['nullable', Rule::exists('brands', 'id')],
            'description' => ['nullable', 'string', 'max:10000'],
            'model' => ['nullable', 'string', 'max:190'],
            'specifications' => ['nullable', 'json', 'max:20000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->filled('category_id')
                && ! Category::query()->publiclyVisible()->whereKey($this->integer('category_id'))->exists()) {
                $validator->errors()->add('category_id', 'التصنيف المحدد غير متاح لإضافة المنتجات.');
            }
        }];
    }
}

<?php

namespace App\Http\Requests\Merchant;

use App\Enums\MerchantVerificationStatus;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\ProductOffer;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->merchant?->verification_status === MerchantVerificationStatus::Verified;
    }

    public function rules(): array
    {
        $newProduct = $this->input('product_mode') === 'new';

        return [
            'product_mode' => ['required', Rule::in(['existing', 'new'])],
            'product_id' => [
                Rule::requiredIf(! $newProduct),
                'nullable',
                Rule::exists('products', 'id')->where(fn ($query) => $query->where('status', ProductStatus::Active->value)),
            ],
            'name' => [Rule::requiredIf($newProduct), 'nullable', 'string', 'max:250'],
            'category_id' => [
                Rule::requiredIf($newProduct),
                'nullable',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->where('status', 'active')),
            ],
            'brand_id' => ['nullable', Rule::exists('brands', 'id')],
            'description' => ['nullable', 'string', 'max:10000'],
            'model' => ['nullable', 'string', 'max:190'],
            'image' => [Rule::requiredIf($newProduct), 'nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
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

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->input('product_mode') === 'new' && $this->filled('category_id')
                && ! Category::query()->publiclyVisible()->whereKey($this->integer('category_id'))->exists()) {
                $validator->errors()->add('category_id', 'التصنيف المحدد غير متاح لإضافة المنتجات.');
            }

            if ($this->input('product_mode') !== 'existing' || ! $this->filled('product_id')) {
                return;
            }

            if (! Product::query()
                ->where('status', ProductStatus::Active->value)
                ->inPublicCategoryOrUncategorized()
                ->whereKey($this->integer('product_id'))
                ->exists()) {
                $validator->errors()->add('product_id', 'المنتج المحدد غير متاح لإضافة عرض جديد.');

                return;
            }

            $merchantId = $this->user()?->merchant?->id;
            if ($merchantId && ProductOffer::query()
                ->where('merchant_id', $merchantId)
                ->where('product_id', $this->integer('product_id'))
                ->exists()) {
                $validator->errors()->add('product_id', 'لديك عرض مسجل لهذا المنتج بالفعل.');
            }
        }];
    }
}

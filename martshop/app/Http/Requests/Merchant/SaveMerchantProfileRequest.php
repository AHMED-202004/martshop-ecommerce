<?php

namespace App\Http\Requests\Merchant;

use App\Models\Merchant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveMerchantProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $merchant = $this->user()?->merchant()->first();

        return $merchant === null || $this->user()->can('update', $merchant);
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password:web'],
            'legal_name' => ['required', 'string', 'max:190'],
            'identity_number' => ['required', 'string', 'min:5', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:'.now()->subYears(18)->toDateString()],
            'location_id' => [
                'required',
                Rule::exists('locations', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'address' => ['required', 'string', 'max:500'],
            'business_type' => ['required', 'string', 'max:120'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $hash = Merchant::identityHash((string) $this->input('identity_number'));
            $query = Merchant::where('identity_number_hash', $hash);
            if ($merchant = $this->user()?->merchant()->first()) {
                $query->whereKeyNot($merchant->id);
            }
            if ($query->exists()) {
                $validator->errors()->add('identity_number', 'رقم الهوية مستخدم في طلب تاجر آخر.');
            }
        }];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $fields = ['first_name', 'last_name', 'governorate', 'city', 'address', 'mobile', 'alt_mobile'];
        $normalized = [];
        foreach ($fields as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $value = trim($this->input($field));
                $normalized[$field] = $field === 'alt_mobile' && $value === '' ? null : $value;
            }
        }
        $this->merge($normalized);
    }

    public function rules(): array
    {
        $text = ['required', 'string', 'not_regex:/[\x00-\x1F\x7F]/u'];
        $phone = ['string', 'max:30', 'regex:/^(?=(?:\D*\d){7,})[0-9+\s().-]+$/'];

        return [
            'current_password' => ['required', 'string', 'current_password:web'],
            'first_name' => [...$text, 'max:100'],
            'last_name' => [...$text, 'max:100'],
            'governorate' => [...$text, 'max:100'],
            'city' => [...$text, 'max:120'],
            'address' => [...$text, 'max:255'],
            'mobile' => ['required', ...$phone],
            'alt_mobile' => ['nullable', ...$phone, 'different:mobile'],
        ];
    }

    public function messages(): array
    {
        return [
            '*.required' => 'هذا الحقل مطلوب.',
            '*.max' => 'القيمة أطول من الحد المسموح.',
            '*.not_regex' => 'القيمة تحتوي محارف غير مسموحة.',
            'mobile.regex' => 'أدخل رقم جوال صالحًا يحتوي سبعة أرقام على الأقل.',
            'alt_mobile.regex' => 'أدخل رقمًا إضافيًا صالحًا يحتوي سبعة أرقام على الأقل.',
            'alt_mobile.different' => 'يجب أن يختلف الرقم الإضافي عن رقم الجوال.',
            'current_password.current_password' => 'كلمة المرور الحالية غير صحيحة.',
        ];
    }
}

<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class RegisterAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() === null;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (['register_login', 'first_name', 'last_name', 'governorate', 'city', 'address', 'mobile', 'alt_mobile'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $value = trim($this->input($field));
                $normalized[$field] = $field === 'alt_mobile' && $value === '' ? null : $value;
            }
        }
        if (isset($normalized['register_login']) && filter_var($normalized['register_login'], FILTER_VALIDATE_EMAIL)) {
            $normalized['register_login'] = mb_strtolower($normalized['register_login']);
        }
        $this->merge($normalized);
    }

    public function rules(): array
    {
        $login = (string) $this->input('register_login');
        $emailLogin = filter_var($login, FILTER_VALIDATE_EMAIL) !== false;
        $text = ['required', 'string', 'not_regex:/[\x00-\x1F\x7F]/u'];
        $phone = ['string', 'max:30', 'regex:/^(?=(?:\D*\d){7,})[0-9+\s().-]+$/'];

        return [
            'register_login' => ['required', 'string', 'max:191',
                $emailLogin ? 'email' : $phone[2],
                Rule::unique('users', $emailLogin ? 'email' : 'phone')],
            'first_name' => [...$text, 'max:100'],
            'last_name' => [...$text, 'max:100'],
            'password' => ['required', 'string', 'max:72', Password::min(12)->letters()->numbers()],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'dob_day' => ['required', 'integer', 'between:1,31'],
            'dob_month' => ['required', 'integer', 'between:1,12'],
            'dob_year' => ['required', 'integer', 'between:1900,'.now()->year],
            'governorate' => [...$text, 'max:100'],
            'city' => [...$text, 'max:120'],
            'address' => [...$text, 'max:255'],
            'mobile' => ['required', ...$phone],
            'alt_mobile' => ['nullable', ...$phone, 'different:mobile'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $day = filter_var($this->input('dob_day'), FILTER_VALIDATE_INT);
            $month = filter_var($this->input('dob_month'), FILTER_VALIDATE_INT);
            $year = filter_var($this->input('dob_year'), FILTER_VALIDATE_INT);
            if ($day && $month && $year && ! checkdate($month, $day, $year)) {
                $validator->errors()->add('dob_day', 'تاريخ الميلاد غير صالح.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'register_login.email' => 'أدخل بريدًا إلكترونيًا صحيحًا.',
            'register_login.regex' => 'أدخل رقم هاتف صالحًا يحتوي سبعة أرقام على الأقل.',
            'register_login.unique' => 'بيانات تسجيل الدخول مستخدمة مسبقًا.',
            'password.min' => 'استخدم 12 حرفًا على الأقل.',
            'password.letters' => 'يجب أن تحتوي كلمة المرور على حروف.',
            'password.numbers' => 'يجب أن تحتوي كلمة المرور على أرقام.',
            'mobile.regex' => 'أدخل رقم جوال صالحًا يحتوي سبعة أرقام على الأقل.',
            'alt_mobile.regex' => 'أدخل رقمًا إضافيًا صالحًا يحتوي سبعة أرقام على الأقل.',
            'alt_mobile.different' => 'يجب أن يختلف الرقم الإضافي عن رقم الجوال.',
        ];
    }
}

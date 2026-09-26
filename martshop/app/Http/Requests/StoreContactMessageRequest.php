<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (['topic', 'contact', 'ref', 'message'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $value = trim($this->input($field));
                $normalized[$field] = $field === 'ref' && $value === '' ? null : $value;
            }
        }
        $this->merge($normalized);
    }

    public function rules(): array
    {
        return [
            'topic' => ['required', 'string', Rule::in(self::topics())],
            'contact' => ['required', 'string', 'max:190', 'not_regex:/[\x00-\x1F\x7F]/u',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        return;
                    }
                    $validPhone = mb_strlen($value) <= 30
                        && preg_match('/^(?=(?:\D*\d){7,})[0-9+\s().-]+$/', $value) === 1;
                    if (! $validPhone) {
                        $fail('أدخل بريدًا إلكترونيًا صحيحًا أو رقم هاتف صالحًا يحتوي سبعة أرقام على الأقل.');
                    }
                }],
            'ref' => ['nullable', 'string', 'max:190', 'not_regex:/[\x00-\x1F\x7F]/u'],
            'message' => ['required', 'string', 'min:5', 'max:5000', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'],
        ];
    }

    public function messages(): array
    {
        return [
            'contact.required' => 'يرجى إدخال البريد الإلكتروني أو رقم الهاتف.',
            'message.required' => 'يرجى كتابة رسالتك.',
        ];
    }

    public static function topics(): array
    {
        return [
            'الخصوصية وحذف البيانات',
            'الدعم الفني',
            'أنا تاجر جملة',
            'خدمة الزبائن',
            'شراء بالجملة',
        ];
    }
}

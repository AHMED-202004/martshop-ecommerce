<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangeAccountPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', 'max:72', Password::min(12)->letters()->numbers()],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.current_password' => 'كلمة المرور الحالية غير صحيحة.',
            'password.confirmed' => 'تأكيد كلمة المرور الجديدة غير مطابق.',
            'password.different' => 'اختر كلمة مرور جديدة مختلفة عن الحالية.',
            'password.min' => 'استخدم 12 حرفًا على الأقل.',
            'password.letters' => 'يجب أن تحتوي كلمة المرور على حروف.',
            'password.numbers' => 'يجب أن تحتوي كلمة المرور على أرقام.',
        ];
    }
}

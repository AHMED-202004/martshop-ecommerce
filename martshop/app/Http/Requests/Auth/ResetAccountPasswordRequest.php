<?php

namespace App\Http\Requests\Auth;

use Illuminate\Validation\Rules\Password;

class ResetAccountPasswordRequest extends ForgotPasswordRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'token' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]+$/i'],
            'password' => ['required', 'string', 'confirmed', 'max:72', Password::min(12)->letters()->numbers()],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'token.*' => 'رابط الاستعادة غير صالح. اطلب رابطًا جديدًا.',
            'password.required' => 'أدخل كلمة المرور الجديدة.',
            'password.confirmed' => 'تأكيد كلمة المرور غير مطابق.',
            'password.min' => 'استخدم 12 حرفًا على الأقل.',
            'password.letters' => 'يجب أن تحتوي كلمة المرور على حروف.',
            'password.numbers' => 'يجب أن تحتوي كلمة المرور على أرقام.',
        ];
    }
}

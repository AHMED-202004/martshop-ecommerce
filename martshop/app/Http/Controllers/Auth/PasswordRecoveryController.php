<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetAccountPasswordRequest;
use App\Services\PasswordRecoveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Throwable;

class PasswordRecoveryController extends Controller
{
    public function request(PasswordRecoveryService $service)
    {
        return view('auth.forgot-password', ['mailReady' => $service->mailReady()]);
    }

    public function send(ForgotPasswordRequest $request, PasswordRecoveryService $service)
    {
        if (! $service->mailReady()) {
            return back()->withErrors(['email' => 'إرسال بريد الاستعادة غير مفعّل حاليًا. تواصل مع مسؤول الموقع.'])
                ->withInput($request->only('email'));
        }
        try {
            $service->sendLink($request->validated('email'));
        } catch (Throwable $exception) {
            // Never log transport messages/stack traces that might contain tokens or credentials.
            Log::warning('Password recovery delivery failed.', ['exception_type' => $exception::class]);
        }

        return back()->with('status', 'إذا كان البريد مرتبطًا بحساب مؤهل، ستصلك رسالة الاستعادة. افحص البريد غير المرغوب فيه، أو حاول لاحقًا إذا لم تصل الرسالة.');
    }

    public function form(Request $request, string $token)
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/i', $token), 404);
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:191']]);

        return view('auth.reset-password', ['token' => $token, 'email' => $data['email']]);
    }

    public function reset(ResetAccountPasswordRequest $request, PasswordRecoveryService $service)
    {
        $status = $service->reset($request->validated());
        if ($status !== Password::PASSWORD_RESET) {
            return redirect()->route('password.request')->withErrors(['email' => 'الرابط غير صالح أو انتهت صلاحيته. اطلب رابطًا جديدًا.']);
        }
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'تم تغيير كلمة المرور وإنهاء جلسات الحساب السابقة. سجّل الدخول بكلمة المرور الجديدة.');
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangeAccountPasswordRequest;
use App\Http\Requests\Auth\RegisterAccountRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\MarketplaceSettings;
use App\Services\PasswordRecoveryService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function show(Request $request, MarketplaceSettings $settings)
    {
        $tab = $request->query('tab', 'login');
        if (! in_array($tab, ['login', 'register'], true)) {
            $tab = 'login';
        }
        $registrationEnabled = $settings->boolean('site.registration_enabled');
        $registrationUnavailable = $tab === 'register' && ! $registrationEnabled;
        if ($registrationUnavailable) {
            $tab = 'login';
        }

        return view('auth.account', [
            'tab' => $tab,
            'registrationEnabled' => $registrationEnabled,
            'registrationUnavailable' => $registrationUnavailable,
        ]);
    }

    public function profile(Request $request, MarketplaceSettings $settings)
    {
        return view('auth.profile', [
            'user' => $request->user(),
            'merchantRegistrationEnabled' => $settings->boolean('site.merchant_registration_enabled'),
        ]);
    }

    public function changePassword(ChangeAccountPasswordRequest $request, PasswordRecoveryService $service)
    {
        $service->change(
            $request->user(),
            $request->validated('current_password'),
            $request->validated('password'),
            $request->session()->getId(),
        );
        $request->session()->regenerate();

        return redirect()->route('my-account')
            ->with('success', 'تم تغيير كلمة المرور وإنهاء جلسات الحساب الأخرى.');
    }

    public function login(Request $request)
    {
        $request->validate([
            'login' => ['required', 'string', 'max:191'],
            'password' => ['required', 'string', 'min:5'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $login = trim((string) $request->input('login'));
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $login = mb_strtolower($login);
        }
        $remember = (bool) $request->input('remember', false);

        $credentials = filter_var($login, FILTER_VALIDATE_EMAIL)
            ? ['email' => $login, 'password' => $request->password]
            : ['phone' => $login, 'password' => $request->password];
        $credentials['account_status'] = AccountStatus::Active->value;

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();
            $this->recordLogin($request->user());

            return redirect()->intended('/')->with('toast', 'تم تسجيل الدخول بنجاح');
        }

        $this->recordFailedLogin($login);

        return back()->withErrors(['login' => 'بيانات الدخول غير صحيحة']);
    }

    public function register(RegisterAccountRequest $request, MarketplaceSettings $settings)
    {
        if (! $settings->boolean('site.registration_enabled')) {
            throw ValidationException::withMessages(['register_login' => 'إنشاء الحسابات متوقف مؤقتًا.']);
        }
        $registerLogin = $request->validated('register_login');
        $providedEmail = filter_var($registerLogin, FILTER_VALIDATE_EMAIL) ? true : false;

        // لو كتب إيميل: نخزّنه ونأخذ الهاتف من حقل mobile
        // لو كتب رقم: نخزّن الرقم في phone ونولّد بريدًا بديلًا فريدًا (مهم لـ SQLite لأن email غير nullable)
        if ($providedEmail) {
            $email = strtolower($registerLogin);
            $phone = (string) $request->input('mobile'); // خليه نصًا عاديًا
        } else {
            $phone = $registerLogin;
            $digits = preg_replace('/\D+/', '', (string) $phone);
            $email = ($digits ?: ('user'.Str::random(8))).'@noemail.local';
        }

        $dob = sprintf('%04d-%02d-%02d', $request->dob_year, $request->dob_month, $request->dob_day);

        try {
            $user = DB::transaction(function () use ($dob, $email, $phone, $request) {
                $user = User::create([
                    'name' => $request->validated('first_name').' '.$request->validated('last_name'),
                    'first_name' => $request->validated('first_name'),
                    'last_name' => $request->validated('last_name'),
                    'email' => $email,
                    'phone' => $phone,
                    'password' => Hash::make($request->validated('password')),
                    'password_changed_at' => now(),
                    'gender' => $request->validated('gender'),
                    'dob' => $dob,
                    'governorate' => $request->validated('governorate'),
                    'city' => $request->validated('city'),
                    'address' => $request->validated('address'),
                    'mobile' => $request->validated('mobile'),
                    'alt_mobile' => $request->validated('alt_mobile'),
                    'remember_token' => Str::random(60),
                ]);

                if (Schema::hasTable('roles')) {
                    $customerRole = Role::where('slug', 'customer')->first();
                    if ($customerRole) {
                        $user->assignRole($customerRole);
                    }
                }

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'register_login' => 'بيانات تسجيل الدخول مستخدمة مسبقًا.',
            ]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        $this->recordLogin($user);

        return redirect('/')->with('toast', 'تم إنشاء الحساب وتسجيل الدخول بنجاح');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('toast', 'تم تسجيل الخروج');
    }

    private function recordLogin(User $user): void
    {
        User::query()->whereKey($user->getKey())->update([
            'last_login_at' => now(),
            'login_count' => DB::raw('login_count + 1'),
            'failed_login_count' => 0,
        ]);
    }

    private function recordFailedLogin(string $login): void
    {
        $column = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        User::query()->where($column, $login)->update([
            'failed_login_count' => DB::raw('failed_login_count + 1'),
            'last_failed_login_at' => now(),
        ]);
    }
}

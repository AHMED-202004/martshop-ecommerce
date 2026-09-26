<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Models\User;
use App\Notifications\AccountPasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordRecoveryService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function mailReady(): bool
    {
        $url = (string) config('password_recovery.url');
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $transport = config('mail.mailers.'.config('mail.default').'.transport');

        return (bool) config('password_recovery.mail_enabled')
            && in_array($transport, ['smtp', 'sendmail', 'ses', 'ses-v2', 'postmark', 'resend'], true)
            && filter_var($url, FILTER_VALIDATE_URL)
            && in_array($scheme, app()->environment('local', 'testing') ? ['http', 'https'] : ['https'], true)
            && ! parse_url($url, PHP_URL_USER) && ! parse_url($url, PHP_URL_PASS)
            && ! parse_url($url, PHP_URL_QUERY) && ! parse_url($url, PHP_URL_FRAGMENT);
    }

    public function sendLink(string $email): void
    {
        if (! $this->mailReady()) {
            throw new \LogicException('Password recovery mail is not configured.');
        }
        // Same response for unknown, placeholder, throttled and notified accounts.
        // Placeholder registration addresses are not recovery destinations.
        Password::broker('users')->sendResetLink(['email' => $email], function (User $user, string $token) {
            if (! Str::endsWith(strtolower($user->email), '@noemail.local')) {
                $user->notify(new AccountPasswordReset($token));
            }
        });
    }

    public function reset(#[\SensitiveParameter] array $credentials): string
    {
        return DB::transaction(function () use ($credentials) {
            // Serialize resets of one account; validation and token consumption are atomic.
            User::query()->where('email', $credentials['email'])->lockForUpdate()->first();

            return Password::broker('users')->reset($credentials, function (User $user, string $password) {
                $acceptingInvitation = $user->account_status === AccountStatus::Invited;
                $user->forceFill([
                    'password' => Hash::make($password),
                    'password_changed_at' => now(),
                    'remember_token' => Str::random(60),
                    'account_status' => $acceptingInvitation ? AccountStatus::Active : $user->account_status,
                    'account_status_changed_at' => $acceptingInvitation ? now() : $user->account_status_changed_at,
                    'account_status_changed_by' => $acceptingInvitation ? null : $user->account_status_changed_by,
                ])->save();
                if (config('session.driver') === 'database') {
                    DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
                        ->where('user_id', $user->id)->delete();
                }
                $this->audit->record(
                    $acceptingInvitation ? 'staff.invitation_accepted' : 'account.password_reset',
                    $user,
                    before: $acceptingInvitation ? ['account_status' => AccountStatus::Invited->value] : null,
                    after: $acceptingInvitation ? ['account_status' => AccountStatus::Active->value] : null,
                    reason: $acceptingInvitation
                        ? 'Staff invitation accepted using a valid single-use token.'
                        : 'Password reset using a valid single-use token.',
                );
            });
        });
    }

    public function change(
        User $user,
        #[\SensitiveParameter] string $currentPassword,
        #[\SensitiveParameter] string $newPassword,
        string $currentSessionId,
    ): void {
        DB::transaction(function () use ($user, $currentPassword, $newPassword, $currentSessionId) {
            $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            if (! Hash::check($currentPassword, $lockedUser->password)) {
                throw ValidationException::withMessages([
                    'current_password' => 'كلمة المرور الحالية لم تعد صالحة. أعد المحاولة.',
                ]);
            }

            $lockedUser->forceFill([
                'password' => Hash::make($newPassword),
                'password_changed_at' => now(),
                'remember_token' => Str::random(60),
            ])->save();

            DB::table(config('auth.passwords.users.table', 'password_reset_tokens'))
                ->where('email', $lockedUser->email)->delete();

            if (config('session.driver') === 'database') {
                DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
                    ->where('user_id', $lockedUser->id)
                    ->where('id', '!=', $currentSessionId)
                    ->delete();
            }

            $this->audit->record(
                'account.password_changed',
                $lockedUser,
                reason: 'Password changed by the authenticated account owner.',
            );
        });
    }
}

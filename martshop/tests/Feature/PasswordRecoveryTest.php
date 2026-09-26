<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\AccountPasswordReset;
use App\Services\AuditLogger;
use App\Services\PasswordRecoveryService;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['password_recovery.mail_enabled' => true, 'password_recovery.url' => 'https://mart.example',
            'mail.default' => 'smtp']);
    }

    public function test_login_links_to_recovery_and_pages_do_not_load_external_content(): void
    {
        $this->get(route('login'))->assertOk()->assertSee(route('password.request'));
        $requestPage = $this->get(route('password.request'))->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Frame-Options', 'DENY')->assertDontSee('<script', false)->assertDontSee('<img', false)
            ->assertSee('assets/password-recovery.css', false);
        $this->assertStringContainsString("style-src 'self'", $requestPage->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString("style-src 'unsafe-inline'", $requestPage->headers->get('Content-Security-Policy'));
        $user = User::factory()->create();
        $token = Password::broker('users')->createToken($user);
        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')->assertSee('اختيار كلمة مرور جديدة');
    }

    public function test_email_response_does_not_reveal_account_and_delivery_uses_trusted_origin(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'admin@mart.example']);
        $this->from(route('password.request'))->post(route('password.email'), ['email' => ' ADMIN@MART.EXAMPLE '])
            ->assertRedirect(route('password.request'))->assertSessionHas('status');
        $knownStatus = session('status');
        Notification::assertSentTo($user, AccountPasswordReset::class, function ($notification) use ($user) {
            $message = $notification->toMail($user);
            $this->assertStringStartsWith('https://mart.example/reset-password/', $message->actionUrl);
            $this->assertTrue(Hash::check($notification->token, DB::table('password_reset_tokens')->where('email', $user->email)->value('token')));
            $this->assertNotSame($notification->token, DB::table('password_reset_tokens')->where('email', $user->email)->value('token'));

            return true;
        });
        $this->post(route('password.email'), ['email' => 'missing@mart.example'])->assertSessionHas('status', $knownStatus);
        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status', $knownStatus);
        Notification::assertSentToTimes($user, AccountPasswordReset::class, 1);
    }

    public function test_log_mail_and_untrusted_configuration_do_not_create_tokens(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        foreach ([['mail.default', 'log'], ['password_recovery.mail_enabled', false], ['password_recovery.url', '//untrusted.example']] as [$key, $value]) {
            config(['mail.default' => 'smtp', 'password_recovery.mail_enabled' => true, 'password_recovery.url' => 'https://mart.example']);
            config([$key => $value]);
            $this->post(route('password.email'), ['email' => $user->email])->assertSessionHasErrors('email');
        }
        $this->assertDatabaseCount('password_reset_tokens', 0);
        Notification::assertNothingSent();
    }

    public function test_valid_reset_preserves_admin_permissions_rotates_remember_and_revokes_database_sessions(): void
    {
        $this->seed(AuthorizationSeeder::class);
        $previousPasswordChange = now()->subDay();
        $user = User::factory()->create([
            'remember_token' => 'old-remember',
            'password_changed_at' => $previousPasswordChange,
        ]);
        $user->assignRole('admin');
        $other = User::factory()->create();
        $token = Password::broker('users')->createToken($user);
        DB::table('sessions')->insert([
            ['id' => 'old-admin-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 'other-session', 'user_id' => $other->id, 'payload' => '', 'last_activity' => time()],
        ]);
        config(['session.driver' => 'database']);
        $this->post(route('password.update'), $this->credentials($user, $token))->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('NewPassword12345', $user->fresh()->password));
        $this->assertTrue($user->fresh()->password_changed_at->greaterThan($previousPasswordChange));
        $this->assertNotSame('old-remember', $user->fresh()->remember_token);
        $this->assertTrue($user->fresh()->hasPermission('refunds.review'));
        $this->assertDatabaseMissing('sessions', ['id' => 'old-admin-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'other-session']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertGuest();
        $audit = AuditLog::where('action', 'account.password_reset')->sole();
        $this->assertSame($user->id, $audit->subject_id);
        $this->assertStringNotContainsString($token, $audit->toJson());
        $this->assertStringNotContainsString('NewPassword12345', $audit->toJson());
        $this->post(route('login.post'), ['login' => $user->email, 'password' => 'NewPassword12345'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_expired_replaced_wrong_account_and_replayed_tokens_fail(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $old = Password::broker('users')->createToken($user);
        $token = Password::broker('users')->createToken($user);
        $originalPassword = $user->password;
        foreach ([[$user, $old], [$other, $token], [$user, str_repeat('a', 64)]] as [$target, $invalid]) {
            $this->post(route('password.update'), $this->credentials($target, $invalid))->assertSessionHasErrors('email');
        }
        $this->travel(61)->minutes();
        $this->post(route('password.update'), $this->credentials($user, $token))->assertSessionHasErrors('email');
        $this->assertSame($originalPassword, $user->fresh()->password);
        $new = Password::broker('users')->createToken($user);
        $this->post(route('password.update'), $this->credentials($user, $new))->assertRedirect(route('login'));
        $this->travel(2)->minutes();
        $this->post(route('password.update'), $this->credentials($user, $new))->assertSessionHasErrors('email');
        $this->assertSame(1, AuditLog::where('action', 'account.password_reset')->count());
    }

    public function test_validation_does_not_flash_password_or_reset_token_and_login_failure_does_not_flash_password(): void
    {
        $user = User::factory()->create();
        $token = Password::broker('users')->createToken($user);
        $data = $this->credentials($user, $token);
        $data['password_confirmation'] = 'wrong';
        $this->post(route('password.update'), $data)->assertSessionHasErrors('password')
            ->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.token')
            ->assertSessionMissing('_old_input.password_confirmation');
        $this->post(route('login.post'), ['login' => $user->email, 'password' => 'IncorrectSecret'])
            ->assertSessionHasErrors('login')->assertSessionMissing('_old_input.password');
        $this->assertTrue(Password::broker('users')->tokenExists($user, $token));
    }

    public function test_failed_audit_rolls_back_password_and_token_consumption(): void
    {
        $user = User::factory()->create();
        $original = $user->password;
        $token = Password::broker('users')->createToken($user);
        $this->mock(AuditLogger::class)->shouldReceive('record')->andThrow(new \RuntimeException('Audit unavailable'));
        try {
            app(PasswordRecoveryService::class)->reset($this->credentials($user, $token));
            $this->fail('Should fail');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }
        $this->assertSame($original, $user->fresh()->password);
        $this->assertTrue(Password::broker('users')->tokenExists($user, $token));
    }

    public function test_recovery_submissions_are_rate_limited(): void
    {
        Notification::fake();
        for ($i = 0; $i < 3; $i++) {
            $this->post(route('password.email'), ['email' => 'missing@mart.example'])->assertRedirect();
        }
        $this->post(route('password.email'), ['email' => 'missing@mart.example'])
            ->assertTooManyRequests()
            ->assertSee('محاولات كثيرة خلال وقت قصير')
            ->assertDontSee('Illuminate\\')
            ->assertDontSee(base_path());
    }

    private function credentials(User $user, string $token): array
    {
        return ['email' => $user->email, 'token' => $token,
            'password' => 'NewPassword12345', 'password_confirmation' => 'NewPassword12345'];
    }
}

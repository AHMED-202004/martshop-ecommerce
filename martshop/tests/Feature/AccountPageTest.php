<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AccountPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_and_login_page_remains_the_guest_form(): void
    {
        $this->get(route('my-account'))->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk()
            ->assertSee('تسجيل الدخول')
            ->assertSee(route('login', ['tab' => 'register']), false)
            ->assertDontSee('بيانات التواصل');
    }

    public function test_signed_in_user_sees_only_their_profile_and_account_shortcuts(): void
    {
        $other = User::factory()->create([
            'email' => 'other-private@example.test',
            'address' => 'Other private address',
        ]);
        $user = User::factory()->create([
            'first_name' => 'أحمد',
            'last_name' => 'سالم',
            'email' => 'ahmad@example.test',
            'phone' => '0599000000',
            'alt_mobile' => '0569000000',
            'governorate' => 'الخليل',
            'city' => 'دورا',
            'address' => 'وسط البلد',
            'gender' => 'male',
            'dob' => '1994-05-09',
        ]);

        $response = $this->actingAs($user)->get(route('my-account', ['tab' => 'register']))
            ->assertOk()
            ->assertSee('مرحبًا، أحمد سالم')
            ->assertSee('ahmad@example.test')
            ->assertSee('0599000000')
            ->assertSee('الخليل')
            ->assertSee('1994-05-09')
            ->assertSee(route('orders.history'), false)
            ->assertSee(route('address.edit'), false)
            ->assertDontSee(route('register.post'), false)
            ->assertDontSee($other->email)
            ->assertDontSee($other->address)
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString("frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
    }

    public function test_nullable_profile_fields_have_safe_placeholders_and_values_are_escaped(): void
    {
        $user = User::factory()->create([
            'name' => '<script>alert(1)</script>',
            'first_name' => null,
            'last_name' => null,
            'email' => '0599123456@noemail.local',
            'phone' => null,
            'mobile' => null,
            'alt_mobile' => null,
            'governorate' => null,
            'city' => null,
            'address' => null,
            'gender' => null,
            'dob' => null,
        ]);

        $this->actingAs($user)->get(route('my-account'))->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('0599123456@noemail.local')
            ->assertSee('غير مضاف')
            ->assertSee('غير مضافة');
    }

    public function test_owner_changes_password_revokes_other_sessions_and_reset_tokens_but_keeps_permissions(): void
    {
        $this->seed(AuthorizationSeeder::class);
        config(['session.driver' => 'database']);
        $previousPasswordChange = now()->subDay();
        $user = User::factory()->create([
            'remember_token' => 'old-remember',
            'password_changed_at' => $previousPasswordChange,
        ]);
        $user->assignRole('admin');
        $other = User::factory()->create();
        $token = Password::broker('users')->createToken($user);
        DB::table('sessions')->insert([
            ['id' => 'old-owner-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 'other-user-session', 'user_id' => $other->id, 'payload' => '', 'last_activity' => time()],
        ]);

        $this->actingAs($user)->from(route('my-account'))->put(route('account.password.update'), [
            'current_password' => 'password',
            'password' => 'NewAccountPassword123',
            'password_confirmation' => 'NewAccountPassword123',
        ])->assertRedirect(route('my-account'))->assertSessionHas('success');

        $fresh = $user->fresh();
        $this->assertAuthenticatedAs($fresh);
        $this->assertTrue(Hash::check('NewAccountPassword123', $fresh->password));
        $this->assertTrue($fresh->password_changed_at->greaterThan($previousPasswordChange));
        $this->assertArrayNotHasKey('password_changed_at', $fresh->toArray());
        $this->assertNotSame('old-remember', $fresh->remember_token);
        $this->assertTrue($fresh->hasPermission('refunds.review'));
        $this->assertDatabaseMissing('sessions', ['id' => 'old-owner-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'other-user-session']);
        $this->assertFalse(Password::broker('users')->tokenExists($fresh, $token));
        $audit = AuditLog::where('action', 'account.password_changed')->sole();
        $this->assertSame($user->id, $audit->subject_id);
        $this->assertStringNotContainsString('NewAccountPassword123', $audit->toJson());
        $this->assertStringNotContainsString('old-remember', $audit->toJson());
    }

    public function test_wrong_or_reused_password_is_rejected_without_flashing_or_writing_secrets(): void
    {
        $user = User::factory()->create(['remember_token' => 'remember-before']);
        $originalHash = $user->password;

        $this->actingAs($user)->from(route('my-account'))->put(route('account.password.update'), [
            'current_password' => 'incorrect',
            'password' => 'NewAccountPassword123',
            'password_confirmation' => 'NewAccountPassword123',
        ])->assertRedirect(route('my-account'))->assertSessionHasErrors('current_password')
            ->assertSessionMissing('_old_input.current_password')
            ->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.password_confirmation');

        $this->put(route('account.password.update'), [
            'current_password' => 'password',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('password');
        $this->assertSame($originalHash, $user->fresh()->password);
        $this->assertSame('remember-before', $user->fresh()->remember_token);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_audit_failure_rolls_back_password_session_and_reset_token_changes(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        $originalHash = $user->password;
        $token = Password::broker('users')->createToken($user);
        DB::table('sessions')->insert([
            'id' => 'rollback-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time(),
        ]);
        $this->mock(AuditLogger::class)->shouldReceive('record')->once()
            ->andThrow(new \RuntimeException('Audit unavailable'));

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($user)->put(route('account.password.update'), [
                'current_password' => 'password',
                'password' => 'NewAccountPassword123',
                'password_confirmation' => 'NewAccountPassword123',
            ]);
            $this->fail('The audit failure should abort the password change.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }

        $this->assertSame($originalHash, $user->fresh()->password);
        $this->assertDatabaseHas('sessions', ['id' => 'rollback-session']);
        $this->assertTrue(Password::broker('users')->tokenExists($user, $token));
    }

    public function test_password_change_is_authenticated_and_rate_limited(): void
    {
        $this->put(route('account.password.update'), [])->assertRedirect(route('login'));
        $user = User::factory()->create();
        $this->actingAs($user);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->put(route('account.password.update'), [
                'current_password' => 'incorrect',
                'password' => 'AnotherPassword123',
                'password_confirmation' => 'AnotherPassword123',
            ])->assertRedirect();
        }
        $this->put(route('account.password.update'), [
            'current_password' => 'incorrect',
            'password' => 'AnotherPassword123',
            'password_confirmation' => 'AnotherPassword123',
        ])->assertStatus(429);
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}

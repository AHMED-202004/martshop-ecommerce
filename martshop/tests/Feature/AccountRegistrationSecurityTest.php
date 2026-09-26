<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountRegistrationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_form_matches_server_password_and_profile_limits(): void
    {
        $this->get(route('login', ['tab' => 'register']))
            ->assertOk()
            ->assertSee('12 حرفًا على الأقل، وتحتوي حروفًا وأرقامًا')
            ->assertSee('name="password" minlength="12" maxlength="72" autocomplete="new-password"', false)
            ->assertSee('name="register_login"', false)
            ->assertSee('maxlength="191" autocomplete="username"', false)
            ->assertSee('name="mobile"', false)
            ->assertSee('maxlength="30" autocomplete="tel"', false)
            ->assertDontSee('5 أحرف على الأقل');
    }

    public function test_registration_rejects_weak_password_invalid_date_and_malformed_phones_without_flashing_profile(): void
    {
        $data = $this->validData([
            'password' => 'short',
            'dob_day' => 31,
            'dob_month' => 2,
            'mobile' => 'not-a-phone',
        ]);

        $this->from(route('login', ['tab' => 'register']))->post(route('register.post'), $data)
            ->assertRedirect(route('login', ['tab' => 'register']))
            ->assertSessionHasErrors(['password', 'dob_day', 'mobile'])
            ->assertSessionMissing('_old_input.register_login')
            ->assertSessionMissing('_old_input.first_name')
            ->assertSessionMissing('_old_input.address')
            ->assertSessionMissing('_old_input.mobile')
            ->assertSessionMissing('_old_input.password');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_email_registration_is_normalized_atomic_and_receives_customer_role(): void
    {
        $this->seed(AuthorizationSeeder::class);

        $this->post(route('register.post'), $this->validData([
            'register_login' => '  NEW.CUSTOMER@EXAMPLE.TEST  ',
        ]))->assertRedirect('/');

        $user = User::query()->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('new.customer@example.test', $user->email);
        $this->assertSame('0599000000', $user->phone);
        $this->assertSame('أحمد سالم', $user->name);
        $this->assertTrue(Hash::check('SecurePassword123', $user->password));
        $this->assertNotNull($user->password_changed_at);
        $this->assertArrayNotHasKey('password_changed_at', $user->toArray());
        $this->assertTrue($user->hasRole('customer'));
        $this->assertSame(60, strlen($user->remember_token));
    }

    public function test_phone_registration_rejects_duplicate_login_and_uses_non_recoverable_placeholder_email(): void
    {
        User::factory()->create(['phone' => '0599111222']);
        $this->post(route('register.post'), $this->validData(['register_login' => '0599111222']))
            ->assertSessionHasErrors('register_login');

        $this->post(route('register.post'), $this->validData(['register_login' => '+970 599 333 444']))
            ->assertRedirect('/');
        $user = User::query()->where('phone', '+970 599 333 444')->sole();
        $this->assertStringEndsWith('@noemail.local', $user->email);
    }

    public function test_login_trims_and_normalizes_email_without_flashing_identifier_on_failure(): void
    {
        $user = User::factory()->create(['email' => 'customer@example.test']);

        $this->post(route('login.post'), [
            'login' => '  CUSTOMER@EXAMPLE.TEST ', 'password' => 'password',
        ])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);

        auth()->logout();
        $this->post(route('login.post'), [
            'login' => 'private@example.test', 'password' => 'incorrect',
        ])->assertSessionHasErrors('login')->assertSessionMissing('_old_input.login');
    }

    public function test_login_rate_limit_tracks_the_normalized_identifier_across_ip_addresses(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.10.0.{$attempt}"])
                ->post(route('login.post'), [
                    'login' => '  RATE.LIMIT@EXAMPLE.TEST  ',
                    'password' => 'incorrect',
                ])
                ->assertSessionHasErrors('login');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.10.0.99'])
            ->post(route('login.post'), [
                'login' => 'rate.limit@example.test',
                'password' => 'incorrect',
            ])
            ->assertTooManyRequests();
    }

    public function test_authentication_routes_use_named_rate_limiters(): void
    {
        $this->assertContains('throttle:login', app('router')->getRoutes()->getByName('login.post')->gatherMiddleware());
        $this->assertContains('throttle:registration', app('router')->getRoutes()->getByName('register.post')->gatherMiddleware());
        $this->assertContains('throttle:password-recovery', app('router')->getRoutes()->getByName('password.email')->gatherMiddleware());
    }

    private function validData(array $overrides = []): array
    {
        return array_replace([
            'register_login' => 'new@example.test',
            'first_name' => 'أحمد',
            'last_name' => 'سالم',
            'password' => 'SecurePassword123',
            'gender' => 'male',
            'dob_day' => 9,
            'dob_month' => 5,
            'dob_year' => 1994,
            'governorate' => 'الخليل',
            'city' => 'دورا',
            'address' => 'وسط البلد',
            'mobile' => '0599000000',
            'alt_mobile' => '0569000000',
        ], $overrides);
    }
}

<?php

namespace Tests\Feature;

use App\Models\{AuditLog, User};
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_address_pages_require_authentication(): void
    {
        $this->get(route('address.edit'))->assertRedirect(route('login'));
        $this->post(route('address.update'), $this->validData())->assertRedirect(route('login'));
    }

    public function test_owner_sees_only_their_escaped_details_with_private_headers(): void
    {
        $other = User::factory()->create(['address' => 'Another user private address']);
        $user = User::factory()->create([
            'first_name' => '<script>alert(1)</script>',
            'last_name' => 'سالم',
            'address' => 'شارع السوق',
            'mobile' => '0599000000',
        ]);

        $response = $this->actingAs($user)->get(route('address.edit'))->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('شارع السوق')->assertSee('0599000000')
            ->assertDontSee($other->address)
            ->assertSee(route('my-account'), false)
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Frame-Options', 'DENY');

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_owner_updates_normalized_delivery_details_without_changing_login_phone_or_other_user(): void
    {
        $user = User::factory()->create(['phone' => '0599111111']);
        $other = User::factory()->create(['address' => 'Keep this address']);

        $this->actingAs($user)->post(route('address.update'), $this->validData([
            'first_name' => '  أحمد  ',
            'last_name' => '  سالم ',
            'alt_mobile' => ' 0569000000 ',
        ]))->assertRedirect(route('my-account'))->assertSessionHas('success')
            ->assertHeader('Referrer-Policy', 'no-referrer');

        $fresh = $user->fresh();
        $this->assertSame('أحمد سالم', $fresh->name);
        $this->assertSame('أحمد', $fresh->first_name);
        $this->assertSame('سالم', $fresh->last_name);
        $this->assertSame('0599000000', $fresh->mobile);
        $this->assertSame('0569000000', $fresh->alt_mobile);
        $this->assertSame('0599111111', $fresh->phone);
        $this->assertSame('Keep this address', $other->fresh()->address);
        $audit = AuditLog::where('action', 'account.delivery_details_updated')->sole();
        $this->assertSame($user->id, $audit->subject_id);
        foreach (['0599000000', '0569000000', 'شارع السوق'] as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $audit->toJson());
        }
    }

    public function test_invalid_numbers_do_not_change_profile_or_create_audit(): void
    {
        $user = User::factory()->create(['mobile' => '0599111111']);
        $this->actingAs($user)->from(route('address.edit'))->post(route('address.update'), $this->validData([
            'mobile' => 'not-a-phone',
            'alt_mobile' => 'not-a-phone',
        ]))->assertRedirect(route('address.edit'))->assertSessionHasErrors(['mobile', 'alt_mobile']);

        $this->assertSame('0599111111', $user->fresh()->mobile);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_update_requires_current_password_and_does_not_flash_private_profile_fields(): void
    {
        $user = User::factory()->create(['address' => 'Original address']);
        $data = $this->validData(['current_password' => 'wrong-password']);

        $this->actingAs($user)->from(route('address.edit'))->post(route('address.update'), $data)
            ->assertRedirect(route('address.edit'))->assertSessionHasErrors('current_password')
            ->assertSessionMissing('_old_input.current_password')
            ->assertSessionMissing('_old_input.first_name')
            ->assertSessionMissing('_old_input.address')
            ->assertSessionMissing('_old_input.mobile');

        $this->assertSame('Original address', $user->fresh()->address);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_user_serialization_hides_profile_and_contact_information(): void
    {
        $attributes = $this->validData();
        unset($attributes['current_password']);
        $user = User::factory()->create($attributes);

        foreach (['name', 'first_name', 'last_name', 'email', 'phone', 'gender', 'dob',
            'governorate', 'city', 'address', 'mobile', 'alt_mobile', 'password', 'remember_token'] as $key) {
            $this->assertArrayNotHasKey($key, $user->toArray());
        }
    }

    public function test_audit_failure_rolls_back_profile_update(): void
    {
        $user = User::factory()->create(['address' => 'Original address']);
        $this->mock(AuditLogger::class)->shouldReceive('record')->once()
            ->andThrow(new \RuntimeException('Audit unavailable'));
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($user)->post(route('address.update'), $this->validData());
            $this->fail('The audit failure should abort the update.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }

        $this->assertSame('Original address', $user->fresh()->address);
    }

    private function validData(array $overrides = []): array
    {
        return array_replace([
            'first_name' => 'أحمد',
            'last_name' => 'سالم',
            'governorate' => 'الوسطى',
            'city' => 'الزوايدة',
            'address' => 'شارع السوق',
            'mobile' => '0599000000',
            'alt_mobile' => '',
            'current_password' => 'password',
        ], $overrides);
    }
}

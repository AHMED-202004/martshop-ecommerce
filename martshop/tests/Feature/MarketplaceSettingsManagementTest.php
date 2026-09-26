<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\MarketplaceSetting;
use App\Models\User;
use App\Services\MarketplaceSettingsManager;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MarketplaceSettingsManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
    }

    public function test_only_settings_staff_can_view_and_update_whitelisted_settings(): void
    {
        $ordinary = User::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($ordinary)->get(route('admin.settings.edit'))->assertForbidden()
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->actingAs($ordinary)->put(route('admin.settings.update'), $this->payload())->assertForbidden()
            ->assertHeader('X-Frame-Options', 'DENY');
        $response = $this->actingAs($admin)->get(route('admin.settings.edit'))
            ->assertOk()->assertSee('إعدادات المنصة')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString("default-src 'none'", $response->headers->get('Content-Security-Policy'));
        $this->actingAs($admin)->put(route('admin.settings.update'), $this->payload([
            'site' => ['emergency_notice' => 'تنبيه تشغيلي', 'orders_enabled' => false],
        ]))->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('marketplace_settings', ['key' => 'site.emergency_notice', 'value' => 'تنبيه تشغيلي']);
        $this->assertDatabaseHas('marketplace_settings', ['key' => 'site.orders_enabled', 'value' => '0']);
        $this->assertSame(1, AuditLog::query()->where('action', 'settings.updated')->count());
        $this->assertContains('site.orders_enabled', AuditLog::query()->where('action', 'settings.updated')->firstOrFail()->metadata['changed_keys']);
    }

    public function test_settings_changes_require_current_password_and_service_rechecks_permission(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $payload = $this->payload([
            'site' => ['name' => 'Protected Mart'],
            'reason' => 'PRIVATE SETTINGS REASON',
            'current_password' => 'wrong-password',
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.settings.edit'))
            ->put(route('admin.settings.update'), $payload)
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHasErrors('current_password');

        $oldInput = $response->getSession()->getOldInput();
        $this->assertArrayNotHasKey('current_password', $oldInput);
        $this->assertArrayNotHasKey('reason', $oldInput);
        $this->assertDatabaseMissing('marketplace_settings', ['key' => 'site.name']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'settings.updated']);

        $ordinary = User::factory()->create();
        $this->assertThrows(
            fn () => app(MarketplaceSettingsManager::class)->update(['site.name' => 'Bypass'], $ordinary),
            HttpException::class,
        );
        $this->assertDatabaseMissing('marketplace_settings', ['value' => 'Bypass']);
    }

    public function test_disabled_registration_and_orders_are_enforced_server_side(): void
    {
        MarketplaceSetting::query()->create([
            'key' => 'site.registration_enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'features',
        ]);
        MarketplaceSetting::query()->create([
            'key' => 'site.orders_enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'features',
        ]);
        MarketplaceSetting::query()->create([
            'key' => 'site.merchant_registration_enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'features',
        ]);

        $this->get(route('login', ['tab' => 'register']))
            ->assertOk()
            ->assertSee('إنشاء الحسابات متوقف مؤقتًا')
            ->assertSee(route('login', ['tab' => 'login']), false)
            ->assertDontSee(route('register.post'), false)
            ->assertDontSee(route('login', ['tab' => 'register']), false);
        $this->post(route('register.post'), [])->assertSessionHasErrors('register_login');
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('my-account'))
            ->assertOk()
            ->assertDontSee(route('merchant.profile.edit'), false);
        $this->get(route('merchant.profile.edit'))
            ->assertOk()
            ->assertSee('تسجيل تجار جدد متوقف مؤقتًا')
            ->assertDontSee(route('merchant.profile.update'), false);
        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.merchant_registration_enabled', false)
            ->assertJsonPath('data.registration_enabled', false)
            ->assertJsonPath('data.orders_enabled', false);
        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('إنشاء الطلبات الجديدة متوقف مؤقتًا')
            ->assertSee('تأكيد الطلب غير متاح مؤقتًا')
            ->assertDontSee(route('checkout.confirm'), false);
        $this->actingAs($user)->post(route('checkout.confirm'), [
            'payment_method' => 'manual_transfer',
        ])->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_invalid_operational_values_are_not_saved(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $payload = $this->payload();
        $payload['checkout']['reservation_minutes'] = 1;
        $payload['reason'] = '';

        $this->actingAs($admin)->put(route('admin.settings.update'), $payload)
            ->assertSessionHasErrors(['checkout.reservation_minutes', 'reason']);
        $this->assertDatabaseMissing('marketplace_settings', ['key' => 'site.name']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'settings.updated']);
    }

    public function test_invalid_boolean_is_rejected_instead_of_silently_disabling_orders(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin)->putJson(route('admin.settings.update'), $this->payload([
            'site' => ['orders_enabled' => 'not-a-boolean'],
        ]))->assertUnprocessable()->assertJsonValidationErrors('site.orders_enabled');
        $this->assertDatabaseMissing('marketplace_settings', ['key' => 'site.orders_enabled']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'settings.updated']);
    }

    public function test_notice_and_chat_follow_current_settings_when_views_render(): void
    {
        $this->get('/')->assertOk()->assertSee('id="support-widget"', false);
        MarketplaceSetting::query()->updateOrCreate(['key' => 'site.chat_enabled'], ['value' => '0']);
        MarketplaceSetting::query()->updateOrCreate(['key' => 'site.emergency_notice'], ['value' => 'Operational notice <script>unsafe()</script>']);
        $this->get('/')->assertOk()->assertDontSee('id="support-widget"', false)
            ->assertSee('Operational notice &lt;script&gt;unsafe()&lt;/script&gt;', false)
            ->assertDontSee('<script>unsafe()</script>', false);
    }

    public function test_public_support_ui_uses_settings_and_does_not_fake_chat_or_card_payment(): void
    {
        foreach ([
            'site.contact_phone' => '0599000000',
            'site.whatsapp' => '0569000000',
            'site.email' => 'support@example.test',
            'site.support_hours' => '09:00 - 17:00 <script>unsafe()</script>',
        ] as $key => $value) {
            MarketplaceSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('0599000000')
            ->assertSee('0569000000')
            ->assertSee('mailto:support@example.test', false)
            ->assertSee('09:00 - 17:00 &lt;script&gt;unsafe()&lt;/script&gt;', false)
            ->assertDontSee('<script>unsafe()</script>', false)
            ->assertSee(route('contact.create'), false)
            ->assertSee('أرسل استفسارك عبر نموذج التواصل ليُحفظ ويصل إلى فريق الدعم.')
            ->assertSee('role="dialog"', false)
            ->assertSee('aria-labelledby="supportPanelTitle"', false)
            ->assertSee('aria-label="تصغير نافذة الدعم"', false)
            ->assertDontSee('id="chatInput"', false)
            ->assertDontSee('id="chatSend"', false)
            ->assertDontSee('alt="VISA"', false)
            ->assertDontSee('alt="MasterCard"', false);

        $script = file_get_contents(public_path('assets/app.js'));
        $productView = file_get_contents(resource_path('views/product/show.blade.php'));
        $this->assertIsString($script);
        $this->assertIsString($productView);
        $this->assertStringNotContainsString('chatInput', $script.$productView);
        $this->assertStringNotContainsString('chatSend', $script.$productView);
        $this->assertStringNotContainsString('sendMsg', $script);
        $this->assertStringNotContainsString("JSON.parse(btn.dataset.qv", $script);
        $this->assertStringContainsString("aria-labelledby=\"brandQuickViewTitle\"", $script);
        $this->assertStringContainsString("if(e.key === 'Escape'", $script);
        $this->assertStringNotContainsString('name="name"', $productView);
        $this->assertStringNotContainsString('name="price"', $productView);
        $this->assertStringNotContainsString('name="image"', $productView);
        $this->assertSame(1, substr_count($script, "document.querySelectorAll('.size-chip').forEach(btn =>"));
        $this->assertStringContainsString("box.setAttribute('role', 'status')", $script);
    }

    public function test_settlement_controls_are_validated_audited_and_do_not_enable_withdrawals(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $payload = $this->payload();
        $payload['settlement'] = ['dispute_days' => 0, 'auto_release_enabled' => true];
        $this->actingAs($admin)->putJson(route('admin.settings.update'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('settlement.dispute_days');
        $payload['settlement']['dispute_days'] = 14;
        $this->actingAs($admin)->put(route('admin.settings.update'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('marketplace_settings', ['key' => 'settlement.dispute_days', 'value' => '14']);
        $this->assertDatabaseHas('marketplace_settings', ['key' => 'settlement.auto_release_enabled', 'value' => '1']);
        $this->assertDatabaseHas('marketplace_settings', ['key' => 'withdrawals.enabled', 'value' => '0']);
        $this->assertSame('14', AuditLog::query()->where('action', 'settings.updated')->firstOrFail()->after['settlement.dispute_days']);
    }

    private function payload(array $overrides = []): array
    {
        $base = [
            'site' => [
                'name' => 'Mart.ps', 'contact_phone' => '0599000000', 'whatsapp' => '',
                'email' => 'support@example.com', 'support_hours' => '09:00 - 17:00',
                'emergency_notice' => '', 'chat_enabled' => true,
                'registration_enabled' => true, 'merchant_registration_enabled' => true,
                'orders_enabled' => true, 'default_delivery_text' => 'التوصيل حسب المنطقة',
            ],
            'checkout' => [
                'shipping' => ['flat_fee' => '20.00', 'free_threshold' => '250.00'],
                'reservation_minutes' => 30,
            ],
            'support' => ['first_response_sla_minutes' => 240],
            'reason' => 'تحديث تشغيلي',
            'current_password' => 'password',
        ];

        return array_replace_recursive($base, $overrides);
    }
}

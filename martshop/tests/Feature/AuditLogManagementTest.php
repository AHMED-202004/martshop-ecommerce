<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuditLogManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
    }

    public function test_only_authorized_staff_can_view_the_audit_log(): void
    {
        $ordinary = User::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $log = $this->log($admin, 'merchant.approved');

        $this->actingAs($ordinary)->get(route('admin.audit-logs.index'))->assertForbidden()
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->actingAs($ordinary)->get(route('admin.audit-logs.show', $log))->assertForbidden()
            ->assertHeader('X-Frame-Options', 'DENY');
        $this->actingAs($ordinary)->get(route('admin.audit-logs.show', 999999))->assertForbidden();
        $index = $this->actingAs($admin)->get(route('admin.audit-logs.index'))
            ->assertOk()->assertSee('merchant.approved')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString('no-store', $index->headers->get('Cache-Control'));
        $this->assertStringContainsString("default-src 'none'", $index->headers->get('Content-Security-Policy'));
        $this->actingAs($admin)->get(route('admin.audit-logs.show', $log))->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_filters_are_scoped_and_sensitive_values_are_redacted(): void
    {
        $admin = User::factory()->create(['name' => 'Audit Admin', 'email' => 'audit@example.com']);
        $admin->assignRole('admin');
        $target = $this->log($admin, 'payment.approved', [
            'status' => 'pending',
            'password' => 'never-show-this',
            'nested' => ['confirmation_pin' => '1234', 'amount' => 100],
        ], ['status' => 'accepted']);
        $this->log(null, 'system.job_finished');

        $this->actingAs($admin)->get(route('admin.audit-logs.index', [
            'action' => 'payment.approved',
            'actor' => 'audit@example.com',
            'subject_type' => User::class,
            'subject_id' => $admin->id,
            'from' => now()->format('Y-m-d'),
            'to' => now()->format('Y-m-d'),
        ]))->assertOk()->assertSee('payment.approved')->assertSee('1 عملية');

        $this->actingAs($admin)->get(route('admin.audit-logs.show', $target))
            ->assertOk()
            ->assertSee('[محجوب]')
            ->assertSee('&quot;amount&quot;: 100', false)
            ->assertDontSee('never-show-this')
            ->assertDontSee('1234');
    }

    public function test_invalid_date_range_is_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get(route('admin.audit-logs.index', [
            'from' => '2026-09-02', 'to' => '2026-09-01',
        ]))->assertSessionHasErrors('to');
    }

    public function test_end_date_can_be_used_without_a_start_date(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $log = $this->log($admin, 'test.date');
        $this->actingAs($admin)->get(route('admin.audit-logs.index', ['to' => now()->format('Y-m-d')]))
            ->assertOk()->assertViewHas('logs', fn ($logs) => $logs->pluck('id')->all() === [$log->id]);
    }

    public function test_actor_search_matches_underscore_literally(): void
    {
        $admin = User::factory()->create(['email' => 'audit_user@example.com']);
        $admin->assignRole('admin');
        $log = $this->log($admin, 'test.actor');
        $other = User::factory()->create(['email' => 'auditXuser@example.com']);
        $this->log($other, 'test.actor');
        $this->actingAs($admin)->get(route('admin.audit-logs.index', ['actor' => 'audit_user']))
            ->assertOk()->assertViewHas('logs', fn ($logs) => $logs->pluck('id')->all() === [$log->id]);
    }

    public function test_redaction_preserves_shipping_values_but_covers_camel_case_secrets(): void
    {
        $result = \App\Support\AuditLogView::sanitize([
            'checkout.shipping.flat_fee' => '20.00',
            'confirmationPin' => '4321',
            'nested' => ['identityNumber' => 'sensitive-identity', 'accessToken' => 'sensitive-token'],
        ]);
        $this->assertSame('20.00', $result['checkout.shipping.flat_fee']);
        $this->assertSame('[محجوب]', $result['confirmationPin']);
        $this->assertSame('[محجوب]', $result['nested']['identityNumber']);
        $this->assertSame('[محجوب]', $result['nested']['accessToken']);
    }

    public function test_index_loads_only_list_columns_and_keeps_only_validated_filters(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        foreach (range(1, 51) as $index) {
            $this->log($admin, 'test.scoped.'.$index, ['private_payload' => 'PRIVATE-BEFORE-'.$index]);
        }

        $response = $this->actingAs($admin)->get(route('admin.audit-logs.index', [
            'actor' => $admin->email,
            'unexpected' => 'PRIVATE-QUERY-VALUE',
        ]))->assertOk()->assertDontSee('PRIVATE-QUERY-VALUE');

        $response->assertViewHas('logs', function ($logs) {
            $attributes = $logs->firstOrFail()->getAttributes();

            foreach (['before', 'after', 'metadata', 'ip_address', 'user_agent'] as $detailColumn) {
                if (array_key_exists($detailColumn, $attributes)) {
                    return false;
                }
            }

            return str_contains($logs->nextPageUrl(), 'actor=')
                && ! str_contains($logs->nextPageUrl(), 'unexpected=');
        });
    }

    public function test_request_metadata_is_normalized_to_the_audit_schema_limits(): void
    {
        Route::get('/_test/audit-metadata', function (AuditLogger $audit) {
            $audit->record('test.request_metadata');

            return response()->noContent();
        });

        $this->withHeader('User-Agent', str_repeat('A', 1500))
            ->get('/_test/audit-metadata')
            ->assertNoContent();

        $log = AuditLog::query()->where('action', 'test.request_metadata')->sole();
        $this->assertSame(1000, strlen($log->user_agent));
        $this->assertSame('127.0.0.1', $log->ip_address);
    }

    private function log(?User $actor, string $action, ?array $before = null, ?array $after = null): AuditLog
    {
        return AuditLog::query()->create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $actor ? User::class : null,
            'subject_id' => $actor?->id,
            'before' => $before,
            'after' => $after,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Audit test agent',
            'created_at' => now(),
        ]);
    }
}

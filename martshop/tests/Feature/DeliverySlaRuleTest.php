<?php
namespace Tests\Feature;
use App\Models\DeliverySlaRule;
use App\Models\Location;
use App\Models\User;
use App\Services\DeliverySlaResolver;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class DeliverySlaRuleTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->seed(AuthorizationSeeder::class); }
    public function test_admin_manages_rules_and_resolver_prefers_specific_matching_dimensions(): void
    {
        $admin = User::factory()->create(); $admin->assignRole('admin');
        $location = Location::create(['name' => 'مركز غزة', 'slug' => 'gaza-center', 'type' => 'city', 'is_active' => true]);
        DeliverySlaRule::create(['name' => 'قاعدة عامة', 'target_minutes' => 240, 'priority' => 0, 'is_active' => true, 'created_by' => $admin->id]);
        $this->actingAs($admin)->post(route('admin.deliveries.sla-rules.store'), [
            'name' => 'غزة السريع', 'origin_location_id' => $location->id, 'destination_area' => 'غزة',
            'order_type' => 'express', 'delivery_method' => 'courier', 'minimum_distance_km' => 2,
            'maximum_distance_km' => 15, 'target_minutes' => 60, 'priority' => 10, 'is_active' => 1,
            'reason' => 'ضبط مدة المنطقة', 'current_password' => 'password',
        ])->assertRedirect()->assertSessionHas('success');
        $resolver = app(DeliverySlaResolver::class);
        $this->assertSame(60, $resolver->resolve($location->id, 'غزة', 'express', 'courier', 8.0)?->target_minutes);
        $this->assertSame(240, $resolver->resolve($location->id, 'غزة', 'express', 'courier', null)?->target_minutes);
        $this->assertSame(240, $resolver->resolve(null, 'رفح', 'standard', 'courier', null)?->target_minutes);
        $this->assertDatabaseHas('audit_logs', ['action' => 'delivery_sla.created']);
    }
}

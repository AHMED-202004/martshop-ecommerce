<?php

namespace Tests\Feature;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\MerchantOrder;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StaffDepartment;
use App\Models\User;
use App\Services\DeliveryPerformanceService;
use App\Services\DeliveryOutcomeService;
use App\Services\DeliveryConfirmationService;
use App\Services\DeliveryDelayService;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DeliveryPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
    }

    public function test_delivery_performance_uses_only_persisted_timestamps_in_selected_period(): void
    {
        $manager = $this->staffWithPermission('roles.manage', 'performance-manager');
        $worker = User::factory()->create(['employee_number' => 'EMP-000303']);
        $worker->assignRole('delivery-worker');
        $completed = $this->delivery($worker, 120, 110, 80, 20, DeliveryStatus::Delivered, 'PERF-1');
        $completed->forceFill([
            'heading_to_merchant_at' => now()->subMinutes(108),
            'merchant_arrived_at' => now()->subMinutes(100),
            'out_for_delivery_at' => now()->subMinutes(75),
            'customer_arrived_at' => now()->subMinutes(30),
        ])->saveQuietly();
        $this->delivery($worker, 60, 40, 30, null, DeliveryStatus::InTransit, 'PERF-2');
        $this->delivery($worker, 60 * 24 * 40, null, null, null, DeliveryStatus::Assigned, 'PERF-OLD');

        $summary = app(DeliveryPerformanceService::class)->summary($manager, $worker, 'month');
        $this->assertSame(2, $summary['assigned']);
        $this->assertSame(2, $summary['accepted']);
        $this->assertSame(1, $summary['delivered']);
        $this->assertSame(1, $summary['active']);
        $this->assertSame(15.0, $summary['average_response_minutes']);
        $this->assertSame(20.0, $summary['average_pickup_minutes']);
        $this->assertSame(60.0, $summary['average_travel_minutes']);
        $this->assertSame(100.0, $summary['average_total_minutes']);
        $this->assertSame(8.0, $summary['average_to_merchant_minutes']);
        $this->assertSame(20.0, $summary['average_merchant_wait_minutes']);
        $this->assertSame(45.0, $summary['average_delivery_travel_minutes']);
        $this->assertSame(10.0, $summary['average_doorstep_minutes']);
        $this->assertCount(2, $summary['history']);

        $this->actingAs($manager)->get(route('admin.staff.show', [
            'staff' => $worker,
            'performance_period' => 'month',
        ]))->assertOk()->assertSee('أداء التوصيل')->assertSee('15 دقيقة')->assertDontSee('PERF-OLD');
        $this->get(route('admin.staff.show', [
            'staff' => $worker,
            'performance_period' => 'all-time',
        ]))->assertSessionHasErrors('performance_period');
    }

    public function test_delivery_performance_service_rejects_unauthorized_actor(): void
    {
        $worker = User::factory()->create();
        $worker->assignRole('delivery-worker');

        $this->expectException(HttpException::class);
        app(DeliveryPerformanceService::class)->summary($worker, $worker, 'week');
    }

    public function test_terminal_outcomes_and_deadlines_are_recorded_without_counting_historical_unknowns(): void
    {
        $manager = $this->staffWithPermission('roles.manage', 'delivery-outcome-performance-manager');
        $operator = $this->staffWithPermission('deliveries.manage', 'delivery-outcome-operator');
        $worker = User::factory()->create(['employee_number' => 'EMP-000304']);
        $worker->assignRole('delivery-worker');

        $cancelled = $this->delivery($worker, 120, null, null, null, DeliveryStatus::Assigned, 'OUT-CANCEL');
        app(DeliveryOutcomeService::class)->record($cancelled, $operator, DeliveryStatus::Cancelled, 'ألغى العميل الطلب قبل الاستلام', 0);
        $failed = $this->delivery($worker, 110, 100, 80, null, DeliveryStatus::InTransit, 'OUT-FAIL');
        app(DeliveryOutcomeService::class)->record($failed, $operator, DeliveryStatus::Failed, 'تعذر الوصول إلى عنوان العميل', 0);
        $onTime = $this->delivery($worker, 100, 90, 70, 20, DeliveryStatus::Delivered, 'OUT-ONTIME');
        $onTime->forceFill(['expected_delivery_at' => now()->subMinutes(10)])->saveQuietly();
        $onTime->rating()->create(['customer_id' => $onTime->order->user_id, 'rating' => 4, 'created_at' => now()]);
        $late = $this->delivery($worker, 90, 80, 60, 5, DeliveryStatus::Delivered, 'OUT-LATE');
        $late->forceFill(['expected_delivery_at' => now()->subMinutes(15)])->saveQuietly();
        app(DeliveryDelayService::class)->record($late, $operator, 'customer_unavailable', 'customer', 'انتظار العميل', 0);

        $summary = app(DeliveryPerformanceService::class)->summary($manager, $worker, 'week');
        $this->assertSame(1, $summary['cancelled']);
        $this->assertSame(1, $summary['failed']);
        $this->assertSame(0, $summary['returned']);
        $this->assertSame(1, $summary['on_time']);
        $this->assertSame(1, $summary['late']);
        $this->assertSame(50.0, $summary['on_time_rate']);
        $this->assertSame(0, $summary['worker_responsible_late']);
        $this->assertSame(1, $summary['external_responsibility_late']);
        $this->assertSame(66.7, $summary['completion_rate']);
        $this->assertSame(33.3, $summary['failed_rate']);
        $this->assertSame(4.0, $summary['average_rating']);
        $this->assertSame(1, $summary['rating_count']);
        $this->assertSame(80.4, $summary['score']);
        $this->assertSame(0, $summary['active']);
        $this->assertDatabaseHas('delivery_events', ['delivery_id' => $cancelled->id, 'event_type' => 'cancelled']);
        $this->assertSame($operator->id, $failed->fresh()->outcome_by);
        $this->assertArrayNotHasKey('outcome_reason', $failed->fresh()->toArray());
    }

    public function test_worker_records_ordered_delivery_milestones(): void
    {
        $worker = User::factory()->create(['employee_number' => 'EMP-000305']);
        $worker->assignRole('delivery-worker');
        $delivery = $this->delivery($worker, 20, 15, null, null, DeliveryStatus::Accepted, 'MILESTONES');
        $delivery->forceFill(['expected_delivery_at' => now()->addHour()])->saveQuietly();
        $service = app(DeliveryConfirmationService::class);

        $service->recordMilestone($delivery, $worker, 0, 'heading-to-merchant', null);
        $service->recordMilestone($delivery, $worker, 1, 'merchant-arrived', null);
        $service->markPickedUp($delivery, $worker, 2, null);
        $service->markInTransit($delivery, $worker, 3, null);
        $service->recordMilestone($delivery, $worker, 4, 'out-for-delivery', null);
        $service->recordMilestone($delivery, $worker, 5, 'customer-arrived', null);

        $delivery->refresh();
        $this->assertNotNull($delivery->heading_to_merchant_at);
        $this->assertNotNull($delivery->merchant_arrived_at);
        $this->assertNotNull($delivery->out_for_delivery_at);
        $this->assertNotNull($delivery->customer_arrived_at);
        $this->assertSame(6, $delivery->lock_version);
        $this->assertSame(6, $delivery->events()->count());
    }

    public function test_delivery_performance_combines_custom_date_area_and_status_filters(): void
    {
        $manager = $this->staffWithPermission('roles.manage', 'delivery-filter-manager');
        $worker = User::factory()->create();
        $worker->assignRole('delivery-worker');
        $this->delivery($worker, 30, 25, 20, 5, DeliveryStatus::Delivered, 'FILTER-DELIVERED');
        $this->delivery($worker, 25, 20, 15, null, DeliveryStatus::InTransit, 'FILTER-ACTIVE');

        $summary = app(DeliveryPerformanceService::class)->summary(
            $manager, $worker, 'custom', today()->toDateString(), today()->toDateString(), 'Gaza', DeliveryStatus::Delivered,
        );
        $this->assertSame(1, $summary['assigned']);
        $this->assertSame(1, $summary['delivered']);
        $this->assertSame('Gaza', $summary['area']);
        $this->assertSame('delivered', $summary['status_filter']);
        $this->assertCount(1, $summary['history']);

        $this->actingAs($manager)->get(route('admin.staff.show', [
            'staff' => $worker, 'performance_period' => 'custom',
            'performance_from' => today()->subDays(367)->toDateString(), 'performance_to' => today()->toDateString(),
        ]))->assertSessionHasErrors('performance_to');
    }

    public function test_performance_comparison_is_limited_to_same_department_and_work_type(): void
    {
        $manager = $this->staffWithPermission('roles.manage', 'comparison-manager');
        $department = StaffDepartment::create(['name' => 'Delivery North', 'slug' => 'delivery-north', 'is_active' => true]);
        $otherDepartment = StaffDepartment::create(['name' => 'Delivery South', 'slug' => 'delivery-south', 'is_active' => true]);
        $worker = User::factory()->create(['staff_department_id' => $department->id, 'employee_number' => 'CMP-1']);
        $peer = User::factory()->create(['staff_department_id' => $department->id, 'employee_number' => 'CMP-2']);
        $outsider = User::factory()->create(['staff_department_id' => $otherDepartment->id, 'employee_number' => 'CMP-3']);
        foreach ([$worker, $peer, $outsider] as $courier) $courier->assignRole('delivery-worker');
        $this->delivery($worker, 30, 25, 20, 5, DeliveryStatus::Delivered, 'CMP-A');
        $this->delivery($peer, 40, 35, 30, 10, DeliveryStatus::Delivered, 'CMP-B');

        $this->actingAs($manager)->get(route('admin.staff.show', ['staff' => $worker, 'compare_staff' => $peer->id]))
            ->assertOk()->assertSee('مقارنة داخل القسم ونوع العمل')->assertSee('CMP-2');
        $this->get(route('admin.staff.show', ['staff' => $worker, 'compare_staff' => $outsider->id]))
            ->assertSessionHasErrors('compare_staff');
    }

    private function delivery(
        User $worker,
        int $assignedMinutesAgo,
        ?int $acceptedMinutesAgo,
        ?int $pickedUpMinutesAgo,
        ?int $deliveredMinutesAgo,
        DeliveryStatus $status,
        string $reference,
    ): Delivery {
        $customer = User::factory()->create();
        $order = Order::create([
            'user_id' => $customer->id,
            'subtotal' => 100,
            'shipping_fee' => 20,
            'total' => 120,
            'currency' => 'ILS',
            'status' => 'confirmed',
            'payment_method' => 'manual_transfer',
            'payment_status' => 'paid',
        ]);
        $merchantOrder = MerchantOrder::create([
            'order_id' => $order->id,
            'group_key' => 'platform-'.$reference,
            'status' => 'confirmed',
            'product_subtotal' => 100,
            'delivery_fee' => 20,
            'total' => 120,
            'currency' => 'ILS',
        ]);

        return Delivery::create([
            'order_id' => $order->id,
            'merchant_order_id' => $merchantOrder->id,
            'delivery_worker_id' => $worker->id,
            'status' => $status,
            'reference' => $reference,
            'assignment_key' => 'key-'.$reference,
            'origin_snapshot' => ['city' => 'Gaza'],
            'destination_snapshot' => ['city' => 'Gaza'],
            'assigned_at' => now()->subMinutes($assignedMinutesAgo),
            'accepted_at' => $acceptedMinutesAgo === null ? null : now()->subMinutes($acceptedMinutesAgo),
            'picked_up_at' => $pickedUpMinutesAgo === null ? null : now()->subMinutes($pickedUpMinutesAgo),
            'in_transit_at' => $pickedUpMinutesAgo === null ? null : now()->subMinutes($pickedUpMinutesAgo),
            'delivered_at' => $deliveredMinutesAgo === null ? null : now()->subMinutes($deliveredMinutesAgo),
        ]);
    }

    private function staffWithPermission(string $permission, string $roleSlug): User
    {
        $role = Role::create(['name' => $roleSlug, 'slug' => $roleSlug]);
        $role->permissions()->attach(Permission::where('slug', $permission)->sole());
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}

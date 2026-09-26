<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\DeliveryStatus;
use App\Enums\StaffTaskPriority;
use App\Enums\StaffTaskStatus;
use App\Models\Delivery;
use App\Models\MerchantOrder;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StaffDepartment;
use App\Models\StaffTask;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagedStaffDepartmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
    }

    public function test_manager_sees_only_minimized_staff_from_departments_they_manage(): void
    {
        $manager = $this->operationalUser('managed-department-manager');
        $manager->directPermissions()->attach(Permission::where('slug', 'staff-departments.view-managed')->sole());
        $otherManager = $this->operationalUser('other-department-manager');
        $mine = StaffDepartment::create([
            'name' => 'الدعم', 'slug' => 'support', 'manager_id' => $manager->id, 'is_active' => true,
        ]);
        $other = StaffDepartment::create([
            'name' => 'المالية', 'slug' => 'finance', 'manager_id' => $otherManager->id, 'is_active' => true,
        ]);
        $employee = User::factory()->create([
            'name' => 'Scoped Employee',
            'email' => 'PRIVATE-EMAIL@example.test',
            'phone' => 'PRIVATE-PHONE',
            'employee_number' => 'EMP-000501',
            'job_title' => 'Support Agent',
            'staff_department_id' => $mine->id,
            'account_status' => AccountStatus::Active,
        ]);
        $employee->assignRole('delivery-worker');
        $outside = User::factory()->create([
            'name' => 'Outside Employee',
            'employee_number' => 'EMP-000502',
            'staff_department_id' => $other->id,
        ]);
        $outside->assignRole('delivery-worker');
        $customerInDepartment = User::factory()->create([
            'name' => 'PRIVATE CUSTOMER IN DEPARTMENT',
            'staff_department_id' => $mine->id,
        ]);
        $customerInDepartment->assignRole('customer');
        StaffTask::create([
            'title' => 'Overdue scoped task',
            'description' => 'PRIVATE TASK DESCRIPTION',
            'notes' => 'PRIVATE MANAGER NOTE',
            'task_type' => 'support',
            'priority' => StaffTaskPriority::High,
            'assigned_to' => $employee->id,
            'assigned_by' => $manager->id,
            'status' => StaffTaskStatus::Assigned,
            'due_at' => now()->subHour(),
        ]);
        StaffTask::create([
            'title' => 'Outside department task',
            'task_type' => 'finance',
            'priority' => StaffTaskPriority::Normal,
            'assigned_to' => $outside->id,
            'assigned_by' => $otherManager->id,
            'status' => StaffTaskStatus::Assigned,
        ]);
        StaffTask::create([
            'title' => 'PRIVATE CUSTOMER TASK',
            'task_type' => 'general',
            'priority' => StaffTaskPriority::Normal,
            'assigned_to' => $customerInDepartment->id,
            'assigned_by' => $manager->id,
            'status' => StaffTaskStatus::Assigned,
        ]);
        $this->delivery($employee, DeliveryStatus::Assigned, 'managed-active', now()->subDay());
        $this->delivery($employee, DeliveryStatus::Delivered, 'managed-delivered', now()->subDays(2));
        $this->delivery($outside, DeliveryStatus::Delivered, 'outside-delivered', now()->subDay());

        $response = $this->actingAs($manager)->get(route('staff-departments.managed'))
            ->assertOk()->assertSee('الدعم')->assertSee('Scoped Employee')->assertSee('EMP-000501')
            ->assertSee('المهام المفتوحة في أقسامي')->assertSee('Overdue scoped task')->assertSee('متأخرة')
            ->assertSee('الاستحقاق (UTC)')
            ->assertSee('Support Agent')->assertDontSee('المالية')->assertDontSee('Outside Employee')
            ->assertDontSee('Outside department task')->assertDontSee('PRIVATE TASK DESCRIPTION')
            ->assertDontSee('PRIVATE CUSTOMER IN DEPARTMENT')->assertDontSee('PRIVATE CUSTOMER TASK')
            ->assertDontSee('PRIVATE MANAGER NOTE')->assertDontSee('PRIVATE-EMAIL')->assertDontSee('PRIVATE-PHONE');
        $response->assertViewHas('departments', function ($departments) {
            $this->assertCount(1, $departments);
            $employee = $departments->first()->staff->sole();
            $this->assertEqualsCanonicalizing(
                ['id', 'name', 'employee_number', 'job_title', 'staff_department_id', 'account_status', 'active_task_count', 'overdue_task_count', 'deliveries_assigned_month_count', 'deliveries_delivered_month_count', 'active_delivery_count'],
                array_keys($employee->getAttributes()),
            );
            $this->assertSame(1, $employee->active_task_count);
            $this->assertSame(1, $employee->overdue_task_count);
            $this->assertSame(2, $employee->deliveries_assigned_month_count);
            $this->assertSame(1, $employee->deliveries_delivered_month_count);
            $this->assertSame(1, $employee->active_delivery_count);

            return true;
        });
        $response->assertViewHas('openTasks', function ($tasks) use ($employee) {
            $task = $tasks->sole();
            $this->assertSame($employee->id, $task->assigned_to);
            $this->assertEqualsCanonicalizing(
                ['id', 'title', 'task_type', 'priority', 'assigned_to', 'due_at', 'status', 'created_at'],
                array_keys($task->getAttributes()),
            );
            $this->assertFalse($task->relationLoaded('related'));
            $this->assertArrayNotHasKey('description', $task->getAttributes());
            $this->assertArrayNotHasKey('notes', $task->getAttributes());

            return true;
        });
        $response->assertViewHas('openTaskTotal', 1);
        $this->get(route('staff-departments.managed', ['overdue' => '1']))
            ->assertOk()->assertSee('Overdue scoped task')->assertDontSee('Outside department task');
        $this->get(route('staff-departments.managed', ['overdue' => '0']))
            ->assertSessionHasErrors('overdue');
    }

    public function test_managed_department_page_requires_explicit_permission(): void
    {
        $worker = User::factory()->create();
        $worker->assignRole('delivery-worker');

        $this->actingAs($worker)->get(route('staff-departments.managed'))->assertForbidden();
    }

    public function test_authorized_manager_without_a_department_sees_no_staff_or_tasks(): void
    {
        $manager = $this->operationalUser('unassigned-manager');
        $manager->directPermissions()->attach(Permission::where('slug', 'staff-departments.view-managed')->sole());
        $outsideManager = $this->operationalUser('assigned-manager');
        $department = StaffDepartment::create([
            'name' => 'قسم خارج النطاق', 'slug' => 'outside-unassigned', 'manager_id' => $outsideManager->id, 'is_active' => true,
        ]);
        $employee = User::factory()->create([
            'name' => 'PRIVATE OUTSIDE STAFF', 'staff_department_id' => $department->id,
        ]);
        $employee->assignRole('delivery-worker');
        StaffTask::create([
            'title' => 'PRIVATE OUTSIDE TASK',
            'task_type' => 'general',
            'priority' => StaffTaskPriority::Normal,
            'assigned_to' => $employee->id,
            'assigned_by' => $outsideManager->id,
            'status' => StaffTaskStatus::Assigned,
        ]);

        $this->actingAs($manager)->get(route('staff-departments.managed'))
            ->assertOk()->assertSee('لا يوجد قسم مسند لإدارتك حاليًا')
            ->assertDontSee('قسم خارج النطاق')->assertDontSee('PRIVATE OUTSIDE STAFF')
            ->assertDontSee('PRIVATE OUTSIDE TASK')
            ->assertViewHas('departments', fn ($departments) => $departments->isEmpty())
            ->assertViewHas('openTasks', fn ($tasks) => $tasks->isEmpty())
            ->assertViewHas('openTaskTotal', 0);
    }

    public function test_managed_task_preview_is_capped_but_reports_full_count(): void
    {
        $manager = $this->operationalUser('managed-task-cap-manager');
        $manager->directPermissions()->attach(Permission::where('slug', 'staff-departments.view-managed')->sole());
        $department = StaffDepartment::create([
            'name' => 'الطلبات', 'slug' => 'orders', 'manager_id' => $manager->id, 'is_active' => true,
        ]);
        $employee = User::factory()->create(['staff_department_id' => $department->id]);
        $employee->assignRole('delivery-worker');
        foreach (range(1, 101) as $number) {
            StaffTask::create([
                'title' => 'Managed task '.$number,
                'task_type' => 'general',
                'priority' => StaffTaskPriority::Normal,
                'assigned_to' => $employee->id,
                'assigned_by' => $manager->id,
                'status' => StaffTaskStatus::Assigned,
            ]);
        }

        $response = $this->actingAs($manager)->get(route('staff-departments.managed'))
            ->assertOk()->assertSee('100 من 101 مهمة');
        $response->assertViewHas('openTaskTotal', 101);
        $response->assertViewHas('openTasks', fn ($tasks) => $tasks->count() === 100);
    }

    public function test_open_task_visibility_follows_the_employees_current_department(): void
    {
        $firstManager = $this->operationalUser('first-transfer-manager');
        $secondManager = $this->operationalUser('second-transfer-manager');
        $permission = Permission::where('slug', 'staff-departments.view-managed')->sole();
        $firstManager->directPermissions()->attach($permission);
        $secondManager->directPermissions()->attach($permission);
        $firstDepartment = StaffDepartment::create([
            'name' => 'القسم الأول', 'slug' => 'first-transfer', 'manager_id' => $firstManager->id, 'is_active' => true,
        ]);
        $secondDepartment = StaffDepartment::create([
            'name' => 'القسم الثاني', 'slug' => 'second-transfer', 'manager_id' => $secondManager->id, 'is_active' => true,
        ]);
        $employee = User::factory()->create(['staff_department_id' => $firstDepartment->id]);
        $employee->assignRole('delivery-worker');
        StaffTask::create([
            'title' => 'Transferred employee task',
            'task_type' => 'general',
            'priority' => StaffTaskPriority::Normal,
            'assigned_to' => $employee->id,
            'assigned_by' => $firstManager->id,
            'status' => StaffTaskStatus::Assigned,
        ]);

        $this->actingAs($firstManager)->get(route('staff-departments.managed'))
            ->assertOk()->assertSee('Transferred employee task')->assertViewHas('openTaskTotal', 1);
        $employee->forceFill(['staff_department_id' => $secondDepartment->id])->save();

        $this->get(route('staff-departments.managed'))
            ->assertOk()->assertDontSee('Transferred employee task')->assertViewHas('openTaskTotal', 0);
        $this->actingAs($secondManager)->get(route('staff-departments.managed'))
            ->assertOk()->assertSee('Transferred employee task')->assertViewHas('openTaskTotal', 1);
    }

    private function delivery(User $worker, DeliveryStatus $status, string $reference, \DateTimeInterface $assignedAt): Delivery
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
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
            'origin_snapshot' => ['city' => 'Nablus'],
            'destination_snapshot' => ['city' => 'Nablus'],
            'assigned_at' => $assignedAt,
            'delivered_at' => $status === DeliveryStatus::Delivered ? $assignedAt : null,
        ]);
    }

    private function operationalUser(string $roleSlug): User
    {
        $role = Role::create(['name' => $roleSlug, 'slug' => $roleSlug]);
        $role->permissions()->attach(Permission::where('slug', 'contact-messages.view')->sole());
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}

<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\StaffTaskPriority;
use App\Enums\StaffTaskStatus;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\Location;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StaffTask;
use App\Models\User;
use App\Services\StaffTaskService;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class StaffTaskManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
    }

    public function test_manager_creates_and_completes_audited_staff_task(): void
    {
        $manager = $this->staffWithPermission('roles.manage', 'task-manager');
        $employee = User::factory()->create(['employee_number' => 'EMP-000101']);
        $employee->assignRole('delivery-worker');
        $contact = ContactMessage::create([
            'topic' => 'delivery',
            'contact' => 'private@example.test',
            'message' => 'Private customer message',
        ]);

        $this->actingAs($manager)->post(route('admin.staff.tasks.store', $employee), [
            'title' => 'مراجعة تسليمات اليوم',
            'description' => 'وصف داخلي لا يدخل ملخص التدقيق',
            'task_type' => 'support',
            'related_id' => $contact->id,
            'priority' => StaffTaskPriority::High->value,
            'due_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'notes' => 'ملاحظة داخلية',
            'current_password' => 'password',
            'reason' => 'تنظيم عمل التوصيل اليومي',
        ])->assertRedirect(route('admin.staff.show', $employee));

        $task = StaffTask::sole();
        $this->assertSame($employee->id, $task->assigned_to);
        $this->assertSame($manager->id, $task->assigned_by);
        $this->assertSame(StaffTaskStatus::Assigned, $task->status);
        $this->assertSame(StaffTaskPriority::High, $task->priority);
        $this->assertSame($contact->getMorphClass(), $task->related_type);
        $this->assertSame($contact->id, $task->related_id);
        $this->assertArrayNotHasKey('description', $task->toArray());
        $this->assertArrayNotHasKey('notes', $task->toArray());

        $createdAudit = AuditLog::where('action', 'staff.task_created')->sole();
        $this->assertArrayNotHasKey('description', $createdAudit->after);
        $this->assertArrayNotHasKey('notes', $createdAudit->after);

        $this->patch(route('admin.staff.tasks.update', $task), [
            'expected_version' => 0,
            'assigned_to' => $employee->id,
            'priority' => StaffTaskPriority::Urgent->value,
            'due_at' => now()->addHours(8)->format('Y-m-d H:i:s'),
            'status' => StaffTaskStatus::InProgress->value,
            'current_password' => 'password',
            'reason' => 'بدأ الموظف تنفيذ المهمة',
        ])->assertRedirect(route('admin.staff.show', $employee));
        $this->assertNotNull($task->fresh()->started_at);

        $this->patch(route('admin.staff.tasks.update', $task), [
            'expected_version' => 1,
            'assigned_to' => $employee->id,
            'priority' => StaffTaskPriority::Urgent->value,
            'due_at' => '',
            'status' => StaffTaskStatus::Completed->value,
            'current_password' => 'password',
            'reason' => 'اكتملت مراجعة التسليمات',
        ])->assertRedirect(route('admin.staff.show', $employee));
        $this->assertNotNull($task->fresh()->completed_at);
        $this->assertDatabaseCount('audit_logs', 3);
        $this->get(route('admin.staff.show', $employee))
            ->assertOk()->assertSee('مراجعة تسليمات اليوم')->assertSee('مكتملة');
    }

    public function test_task_queue_is_minimized_permission_scoped_and_filters_literals(): void
    {
        $manager = $this->staffWithPermission('roles.manage', 'queue-manager');
        $employee = User::factory()->create(['employee_number' => 'EMP-000202']);
        $employee->assignRole('delivery-worker');
        $literal = StaffTask::create([
            'title' => 'Follow % literal',
            'description' => 'PRIVATE DESCRIPTION',
            'notes' => 'PRIVATE NOTES',
            'task_type' => 'support',
            'priority' => StaffTaskPriority::Urgent,
            'assigned_to' => $employee->id,
            'assigned_by' => $manager->id,
            'due_at' => now()->subHour(),
            'status' => StaffTaskStatus::Assigned,
        ]);
        StaffTask::create([
            'title' => 'Follow X literal',
            'task_type' => 'general',
            'priority' => StaffTaskPriority::Normal,
            'assigned_to' => $employee->id,
            'assigned_by' => $manager->id,
            'status' => StaffTaskStatus::Completed,
            'completed_at' => now(),
        ]);

        $this->actingAs($employee)->get(route('admin.staff.tasks.index'))->assertForbidden();
        $response = $this->actingAs($manager)->get(route('admin.staff.tasks.index', [
            'q' => '%',
            'priority' => 'urgent',
            'assigned_to' => $employee->id,
            'overdue' => '1',
        ]))->assertOk()->assertSee($literal->title)->assertDontSee('Follow X literal')
            ->assertDontSee('PRIVATE DESCRIPTION')->assertDontSee('PRIVATE NOTES');
        $response->assertViewHas('tasks', function ($tasks) use ($literal) {
            $this->assertSame(1, $tasks->total());
            $this->assertSame($literal->id, $tasks->first()->id);
            $this->assertEqualsCanonicalizing(
                ['id', 'title', 'task_type', 'priority', 'assigned_to', 'assigned_by', 'due_at', 'started_at', 'completed_at', 'status', 'created_at'],
                array_keys($tasks->first()->getAttributes()),
            );
            $this->assertTrue($tasks->first()->relationLoaded('assignee'));
            $this->assertTrue($tasks->first()->relationLoaded('assigner'));

            return true;
        });
        $this->get(route('admin.staff.tasks.index', ['status' => 'not-a-status']))
            ->assertSessionHasErrors('status');
    }

    public function test_reassignment_requires_active_operational_staff_and_assigned_status(): void
    {
        $manager = $this->staffWithPermission('roles.manage', 'reassignment-manager');
        $first = User::factory()->create();
        $first->assignRole('delivery-worker');
        $second = User::factory()->create();
        $second->assignRole('delivery-worker');
        $inactive = User::factory()->create(['account_status' => AccountStatus::Suspended]);
        $inactive->assignRole('delivery-worker');
        $task = StaffTask::create([
            'title' => 'مهمة قابلة لإعادة الإسناد',
            'task_type' => 'general',
            'priority' => StaffTaskPriority::Normal,
            'assigned_to' => $first->id,
            'assigned_by' => $manager->id,
            'status' => StaffTaskStatus::Assigned,
        ]);

        $this->actingAs($manager)->patch(route('admin.staff.tasks.update', $task), [
            'expected_version' => 0,
            'assigned_to' => $second->id,
            'priority' => 'normal',
            'due_at' => '',
            'status' => 'in_progress',
            'current_password' => 'password',
            'reason' => 'حالة خاطئة عند إعادة الإسناد',
        ])->assertSessionHasErrors('status');
        $this->assertSame($first->id, $task->fresh()->assigned_to);

        $this->patch(route('admin.staff.tasks.update', $task), [
            'expected_version' => 0,
            'assigned_to' => $inactive->id,
            'priority' => 'normal',
            'due_at' => '',
            'status' => 'assigned',
            'current_password' => 'password',
            'reason' => 'موظف غير متاح للمهمة',
        ])->assertSessionHasErrors('assigned_to');

        $this->patch(route('admin.staff.tasks.update', $task), [
            'expected_version' => 0,
            'assigned_to' => $second->id,
            'priority' => 'high',
            'due_at' => '',
            'status' => 'assigned',
            'current_password' => 'password',
            'reason' => 'نقل المهمة لموظف متاح',
        ])->assertRedirect(route('admin.staff.show', $second));
        $this->assertSame($second->id, $task->fresh()->assigned_to);
    }

    public function test_task_mutations_require_permission_reauthentication_and_open_task(): void
    {
        $manager = $this->staffWithPermission('roles.manage', 'guarded-task-manager');
        $employee = User::factory()->create();
        $employee->assignRole('delivery-worker');

        $this->actingAs($employee)->post(route('admin.staff.tasks.store', $employee), [])->assertForbidden();
        $this->actingAs($manager)->post(route('admin.staff.tasks.store', $employee), [
            'title' => 'لن تنشأ',
            'task_type' => 'general',
            'priority' => 'normal',
            'current_password' => 'wrong-password',
            'reason' => 'رفض كلمة المرور الخاطئة',
        ])->assertSessionHasErrors('current_password')
            ->assertSessionMissing('_old_input.current_password')
            ->assertSessionMissing('_old_input.reason');
        $this->assertDatabaseCount('staff_tasks', 0);

        $this->post(route('admin.staff.tasks.store', $employee), [
            'title' => 'مرجع عام غير صالح',
            'task_type' => 'general',
            'related_id' => 1,
            'priority' => 'normal',
            'current_password' => 'password',
            'reason' => 'رفض مرجع بلا نوع مطابق',
        ])->assertSessionHasErrors('related_id');
        $this->assertDatabaseCount('staff_tasks', 0);

        $task = StaffTask::create([
            'title' => 'مهمة مغلقة',
            'task_type' => 'general',
            'priority' => StaffTaskPriority::Normal,
            'assigned_to' => $employee->id,
            'assigned_by' => $manager->id,
            'status' => StaffTaskStatus::Cancelled,
        ]);
        $this->patch(route('admin.staff.tasks.update', $task), [
            'expected_version' => 0,
            'assigned_to' => $employee->id,
            'priority' => 'normal',
            'due_at' => '',
            'status' => 'assigned',
            'current_password' => 'password',
            'reason' => 'محاولة إعادة فتح مهمة مغلقة',
        ])->assertSessionHasErrors('task');

        $this->expectException(HttpException::class);
        app(StaffTaskService::class)->create(
            $employee,
            $employee->id,
            'Direct service call',
            null,
            'general',
            StaffTaskPriority::Normal,
            null,
            null,
            null,
            'unauthorized direct call',
        );
    }

    public function test_open_task_blocks_termination_until_it_is_reassigned(): void
    {
        $manager = $this->staffWithPermission('roles.manage', 'task-termination-manager');
        $employee = User::factory()->create();
        $employee->assignRole('delivery-worker');
        $replacement = User::factory()->create();
        $replacement->assignRole('delivery-worker');
        $task = StaffTask::create([
            'title' => 'عمل يجب نقله قبل الإنهاء',
            'task_type' => 'general',
            'priority' => StaffTaskPriority::Normal,
            'assigned_to' => $employee->id,
            'assigned_by' => $manager->id,
            'status' => StaffTaskStatus::Assigned,
        ]);

        $this->actingAs($manager)->post(route('admin.staff.terminate', $employee), [
            'confirmation' => $employee->email,
            'current_password' => 'password',
            'reason' => 'محاولة إنهاء مع مهمة مفتوحة',
        ])->assertSessionHasErrors('reassign_tasks_to');
        $this->assertSame(AccountStatus::Active, $employee->fresh()->account_status);

        $this->post(route('admin.staff.terminate', $employee), [
            'confirmation' => $employee->email,
            'reassign_tasks_to' => $replacement->id,
            'current_password' => 'password',
            'reason' => 'انتهاء الخدمة بعد نقل العمل',
        ])->assertRedirect(route('admin.staff.show', $employee));
        $this->assertSame(AccountStatus::Terminated, $employee->fresh()->account_status);
        $this->assertSame($replacement->id, $task->fresh()->assigned_to);
        $this->assertSame(1, $task->fresh()->version);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'staff.task_reassigned_on_termination',
            'subject_id' => $task->id,
        ]);
    }

    public function test_employee_views_only_own_tasks_without_internal_notes_and_updates_status(): void
    {
        $manager = $this->staffWithPermission('roles.manage', 'self-service-manager');
        $employee = User::factory()->create();
        $employee->assignRole('delivery-worker');
        $location = Location::create(['name' => 'نابلس', 'slug' => 'nablus', 'type' => 'city', 'is_active' => true]);
        $employee->staffLocations()->attach($location);
        $employee->staffWorkSchedules()->create([
            'day_of_week' => 1,
            'is_working' => true,
            'starts_at' => '08:30',
            'ends_at' => '16:30',
            'timezone' => 'Asia/Hebron',
        ]);
        $other = User::factory()->create();
        $other->assignRole('delivery-worker');
        $relatedContact = ContactMessage::create([
            'topic' => 'order',
            'contact' => 'PRIVATE RELATED CONTACT',
            'message' => 'PRIVATE RELATED MESSAGE',
        ]);
        $ownTask = StaffTask::create([
            'title' => 'مهمتي الخاصة',
            'description' => 'وصف المهمة المسموح',
            'notes' => 'PRIVATE MANAGER NOTE',
            'task_type' => 'support',
            'related_type' => $relatedContact->getMorphClass(),
            'related_id' => $relatedContact->id,
            'priority' => StaffTaskPriority::High,
            'assigned_to' => $employee->id,
            'assigned_by' => $manager->id,
            'status' => StaffTaskStatus::Assigned,
            'due_at' => now()->subHour(),
        ]);
        StaffTask::create([
            'title' => 'مهمة موظف آخر',
            'notes' => 'OTHER PRIVATE NOTE',
            'task_type' => 'general',
            'priority' => StaffTaskPriority::Normal,
            'assigned_to' => $other->id,
            'assigned_by' => $manager->id,
            'status' => StaffTaskStatus::Assigned,
        ]);

        $response = $this->actingAs($employee)->get(route('staff-tasks.index'))
            ->assertOk()->assertSee('مهمتي الخاصة')->assertSee('وصف المهمة المسموح')
            ->assertSee('نطاق عملي وجدول الدوام')->assertSee('نابلس')->assertSee('08:30')->assertSee('Asia/Hebron')
            ->assertSee('ملخص مهامي')->assertSee('مفتوحة')->assertSee('متأخرة')->assertSee('مكتملة اليوم')->assertSee('متأخرة فعليًا')
            ->assertSee('الاستحقاق (UTC)')
            ->assertSee('السجل المرتبط')->assertSee('support #'.$relatedContact->id)
            ->assertDontSee('مهمة موظف آخر')->assertDontSee('PRIVATE MANAGER NOTE')
            ->assertDontSee('OTHER PRIVATE NOTE')->assertDontSee('PRIVATE RELATED CONTACT')
            ->assertDontSee('PRIVATE RELATED MESSAGE');
        $response->assertViewHas('tasks', function ($tasks) use ($ownTask) {
            $this->assertSame(1, $tasks->total());
            $this->assertSame($ownTask->id, $tasks->first()->id);
            $this->assertArrayNotHasKey('notes', $tasks->first()->getAttributes());
            $this->assertArrayNotHasKey('related_type', $tasks->first()->getAttributes());
            $this->assertFalse($tasks->first()->relationLoaded('related'));

            return true;
        });
        $response->assertViewHas('workLocations', function ($locations) use ($location) {
            $this->assertSame([$location->id], $locations->pluck('id')->all());
            $this->assertEqualsCanonicalizing(['id', 'name'], array_keys($locations->sole()->getAttributes()));

            return true;
        });
        $response->assertViewHas('taskSummary', [
            'open' => 1,
            'overdue' => 1,
            'completed_today' => 0,
        ]);
        $this->get(route('staff-tasks.index', ['overdue' => '1']))
            ->assertOk()->assertSee('مهمتي الخاصة')->assertDontSee('مهمة موظف آخر');
        $this->get(route('staff-tasks.index', ['overdue' => '0']))
            ->assertSessionHasErrors('overdue');

        $this->patch(route('staff-tasks.update', $ownTask), [
            'expected_version' => 0,
            'status' => StaffTaskStatus::InProgress->value,
            'progress_note' => 'بدأت التنفيذ الآن',
        ])->assertRedirect();
        $ownTask->refresh();
        $this->assertSame(StaffTaskStatus::InProgress, $ownTask->status);
        $this->assertSame(1, $ownTask->version);
        $this->assertNotNull($ownTask->started_at);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $employee->id,
            'action' => 'staff.task_status_updated',
            'subject_id' => $ownTask->id,
            'reason' => 'بدأت التنفيذ الآن',
        ]);
    }

    public function test_employee_cannot_change_another_task_cancel_or_overwrite_newer_version(): void
    {
        $manager = $this->staffWithPermission('roles.manage', 'self-service-guard-manager');
        $employee = User::factory()->create();
        $employee->assignRole('delivery-worker');
        $other = User::factory()->create();
        $other->assignRole('delivery-worker');
        $ownTask = StaffTask::create([
            'title' => 'نسخة محمية',
            'task_type' => 'general',
            'priority' => StaffTaskPriority::Normal,
            'assigned_to' => $employee->id,
            'assigned_by' => $manager->id,
            'status' => StaffTaskStatus::Assigned,
            'version' => 1,
        ]);
        $otherTask = StaffTask::create([
            'title' => 'ليست مهمتي',
            'task_type' => 'general',
            'priority' => StaffTaskPriority::Normal,
            'assigned_to' => $other->id,
            'assigned_by' => $manager->id,
            'status' => StaffTaskStatus::Assigned,
        ]);

        $this->actingAs($employee)->patch(route('staff-tasks.update', $otherTask), [
            'expected_version' => 0,
            'status' => 'completed',
            'progress_note' => 'محاولة على مهمة أخرى',
        ])->assertNotFound();
        $this->patch(route('staff-tasks.update', $ownTask), [
            'expected_version' => 1,
            'status' => 'cancelled',
            'progress_note' => 'لا يملك الموظف الإلغاء',
        ])->assertSessionHasErrors('status');
        $this->patch(route('staff-tasks.update', $ownTask), [
            'expected_version' => 0,
            'status' => 'in_progress',
            'progress_note' => 'صفحة قديمة يجب رفضها',
        ])->assertSessionHasErrors('task');
        $this->assertSame(StaffTaskStatus::Assigned, $ownTask->fresh()->status);
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

<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StaffDepartment;
use App\Models\StaffTask;
use App\Models\User;
use App\Notifications\StaffInvitation;
use App\Services\StaffSecurityService;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Mockery\MockInterface;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class StaffDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
    }

    public function test_staff_directory_requires_the_role_management_permission(): void
    {
        $this->get(route('admin.staff.index'))->assertRedirect(route('login'));

        foreach (['customer', 'merchant', 'delivery-worker'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);
            $this->actingAs($user)->get(route('admin.staff.index'))->assertForbidden();
        }
    }

    public function test_authorized_directory_lists_only_operational_staff_with_minimized_fields(): void
    {
        $manager = $this->staffWithPermission('roles.manage', 'staff-manager');
        $admin = User::factory()->create(['name' => 'Admin Person', 'email' => 'admin.person@example.test']);
        $admin->assignRole('admin');
        $driver = User::factory()->create(['name' => 'Driver Person', 'email' => 'driver.person@example.test']);
        $driver->assignRole('delivery-worker');
        $support = $this->staffWithPermission('contact-messages.view', 'support-agent', '<script>alert(1)</script>');
        $customer = User::factory()->create(['name' => 'Private Customer', 'email' => 'customer@example.test']);
        $customer->assignRole('customer');
        $merchant = User::factory()->create(['name' => 'Private Merchant', 'email' => 'merchant@example.test']);
        $merchant->assignRole('merchant');

        $before = DB::table('users')->count();
        $response = $this->actingAs($manager)->get(route('admin.staff.index'))->assertOk()
            ->assertSee('Admin Person')->assertSee('Driver Person')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('Private Customer')->assertDontSee('Private Merchant')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertViewHas('staff', function ($staff) {
            $this->assertSame(4, $staff->total());
            foreach ($staff as $employee) {
                $this->assertEqualsCanonicalizing(['id', 'name', 'email', 'employee_number', 'job_title', 'staff_department_id', 'account_status', 'created_at', 'active_tasks_count'], array_keys($employee->getAttributes()));
                $this->assertTrue($employee->relationLoaded('roles'));
                $this->assertTrue($employee->relationLoaded('staffDepartment'));
            }

            return true;
        });
        $this->assertSame($before, DB::table('users')->count());
        $this->assertSame($support->id, User::where('email', $support->email)->value('id'));
    }

    public function test_staff_search_treats_sql_wildcards_literally_and_role_filter_is_bounded(): void
    {
        $manager = $this->staffWithPermission('roles.manage', 'directory-manager');
        $literal = $this->staffWithPermission('contact-messages.view', 'literal-role', 'Agent % Literal');
        $other = $this->staffWithPermission('contact-messages.view', 'other-role', 'Agent X Literal');
        $department = StaffDepartment::create(['name' => 'Support', 'slug' => 'support', 'is_active' => true]);
        $literal->forceFill([
            'phone' => '0599000111',
            'employee_number' => 'EMP-004200',
            'staff_department_id' => $department->id,
        ])->save();

        $this->actingAs($manager)->get(route('admin.staff.index', ['q' => '%']))
            ->assertOk()->assertSee($literal->email)->assertDontSee($other->email);
        $this->get(route('admin.staff.index', ['role' => 'literal-role']))
            ->assertOk()->assertSee($literal->email)->assertDontSee($other->email);
        $this->get(route('admin.staff.index', ['role' => 'missing-role']))
            ->assertSessionHasErrors('role');
        $this->get(route('admin.staff.index', ['q' => '0599000111']))
            ->assertOk()->assertSee($literal->email)->assertDontSee($other->email);
        $this->get(route('admin.staff.index', ['q' => 'EMP-004200']))
            ->assertOk()->assertSee($literal->email)->assertDontSee($other->email);
        $this->get(route('admin.staff.index', ['department' => $department->id]))
            ->assertOk()->assertSee($literal->email)->assertDontSee($other->email);
        $this->get(route('admin.staff.index', ['sort' => 'unsafe_column']))
            ->assertSessionHasErrors('sort');

        $literal->forceFill(['account_status' => AccountStatus::Suspended])->save();
        $this->get(route('admin.staff.index', ['status' => AccountStatus::Suspended->value]))
            ->assertOk()->assertSee($literal->email)->assertDontSee($other->email);
        $this->get(route('admin.staff.index', ['status' => 'unknown-status']))
            ->assertSessionHasErrors('status');
    }

    public function test_manager_creates_updates_and_disables_audited_staff_department(): void
    {
        $manager = $this->staffWithPermission('roles.manage', 'department-manager');
        $lead = $this->staffWithPermission('contact-messages.view', 'support-lead', 'Support Lead');

        $this->actingAs($manager)->get(route('admin.staff.departments.index'))
            ->assertOk()->assertSee('أقسام الموظفين')->assertSee('Support Lead');
        $this->post(route('admin.staff.departments.store'), [
            'name' => 'دعم العملاء',
            'slug' => 'customer-support',
            'manager_id' => $lead->id,
            'current_password' => 'password',
            'reason' => 'إنشاء قسم الدعم الرسمي',
        ])->assertRedirect();

        $department = StaffDepartment::where('slug', 'customer-support')->sole();
        $this->assertSame('دعم العملاء', $department->name);
        $this->assertSame($lead->id, $department->manager_id);
        $this->assertTrue($department->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'staff.department_created',
            'subject_type' => $department->getMorphClass(),
            'subject_id' => $department->id,
        ]);

        $this->patch(route('admin.staff.departments.update', $department), [
            'name' => 'الدعم الفني',
            'slug' => 'technical-support',
            'manager_id' => '',
            'is_active' => '0',
            'current_password' => 'password',
            'reason' => 'إعادة تنظيم القسم مؤقتًا',
        ])->assertRedirect();

        $department->refresh();
        $this->assertSame('الدعم الفني', $department->name);
        $this->assertSame('technical-support', $department->slug);
        $this->assertNull($department->manager_id);
        $this->assertFalse($department->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'staff.department_updated',
            'subject_id' => $department->id,
        ]);
    }

    public function test_department_management_requires_permission_password_and_active_staff_manager(): void
    {
        $manager = $this->staffWithPermission('roles.manage', 'guarded-department-manager');
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $this->actingAs($customer)->get(route('admin.staff.departments.index'))->assertForbidden();
        $this->actingAs($manager)->post(route('admin.staff.departments.store'), [
            'name' => 'المالية',
            'slug' => 'finance',
            'manager_id' => $customer->id,
            'current_password' => 'password',
            'reason' => 'اختبار مدير غير تشغيلي',
        ])->assertSessionHasErrors('manager_id');
        $this->assertDatabaseCount('staff_departments', 0);

        $this->post(route('admin.staff.departments.store'), [
            'name' => 'المالية',
            'slug' => 'finance',
            'manager_id' => '',
            'current_password' => 'wrong-password',
            'reason' => 'اختبار كلمة مرور خاطئة',
        ])->assertSessionHasErrors('current_password')
            ->assertSessionMissing('_old_input.current_password')
            ->assertSessionMissing('_old_input.reason');
        $this->assertDatabaseCount('staff_departments', 0);
    }

    public function test_manager_updates_staff_identity_without_changing_employee_number_or_permissions(): void
    {
        $manager = $this->staffWithPermission('roles.manage', 'profile-manager');
        $employee = $this->staffWithPermission('contact-messages.view', 'profile-worker', 'Old Name');
        $employee->forceFill(['employee_number' => 'EMP-009999'])->save();
        $department = StaffDepartment::create([
            'name' => 'العمليات',
            'slug' => 'operations',
            'is_active' => true,
        ]);

        $this->actingAs($manager)->patch(route('admin.staff.profile.update', $employee), [
            'name' => 'New Staff Name',
            'phone' => '0599123456',
            'job_title' => 'منسق عمليات',
            'staff_department_id' => $department->id,
            'current_password' => 'password',
            'reason' => 'نقل الموظف إلى العمليات',
        ])->assertRedirect(route('admin.staff.show', $employee));

        $employee->refresh();
        $this->assertSame('New Staff Name', $employee->name);
        $this->assertSame('0599123456', $employee->phone);
        $this->assertSame('منسق عمليات', $employee->job_title);
        $this->assertSame($department->id, $employee->staff_department_id);
        $this->assertSame('EMP-009999', $employee->employee_number);
        $this->assertTrue($employee->hasPermission('contact-messages.view'));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'staff.profile_updated',
            'subject_id' => $employee->id,
            'reason' => 'نقل الموظف إلى العمليات',
        ]);
        $this->get(route('admin.staff.show', $employee))
            ->assertOk()->assertSee('EMP-009999')->assertSee('منسق عمليات')->assertSee('العمليات');
    }

    public function test_staff_profile_rejects_wrong_password_non_staff_and_terminated_targets(): void
    {
        $manager = $this->staffWithPermission('roles.manage', 'guarded-profile-manager');
        $employee = $this->staffWithPermission('contact-messages.view', 'guarded-profile-worker');
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $payload = [
            'name' => 'Attempted Change',
            'phone' => '',
            'job_title' => '',
            'staff_department_id' => '',
            'current_password' => 'wrong-password',
            'reason' => 'يجب رفض كلمة المرور',
        ];
        $this->actingAs($manager)->patch(route('admin.staff.profile.update', $employee), $payload)
            ->assertSessionHasErrors('current_password')
            ->assertSessionMissing('_old_input.current_password')
            ->assertSessionMissing('_old_input.reason');
        $this->assertNotSame('Attempted Change', $employee->fresh()->name);

        $payload['current_password'] = 'password';
        $this->patch(route('admin.staff.profile.update', $customer), $payload)->assertNotFound();

        $employee->forceFill(['account_status' => AccountStatus::Terminated])->save();
        $this->patch(route('admin.staff.profile.update', $employee), $payload)
            ->assertSessionHasErrors('staff');
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'staff.profile_updated',
            'subject_id' => $employee->id,
        ]);
    }

    public function test_manager_changes_staff_email_and_revokes_sessions_and_recovery_tokens(): void
    {
        config(['session.driver' => 'database']);
        $manager = $this->staffWithPermission('roles.manage', 'email-manager');
        $employee = User::factory()->create([
            'email' => 'old.staff@example.test',
            'email_verified_at' => now(),
            'remember_token' => 'old-email-token',
        ]);
        $employee->assignRole('delivery-worker');
        DB::table('sessions')->insert([
            'id' => 'staff-email-session', 'user_id' => $employee->id, 'payload' => '', 'last_activity' => time(),
        ]);
        Password::broker('users')->createToken($employee);

        $this->actingAs($manager)->patch(route('admin.staff.email.update', $employee), [
            'email' => ' NEW.STAFF@EXAMPLE.TEST ',
            'current_password' => 'password',
            'reason' => 'تحديث البريد الرسمي للموظف',
        ])->assertRedirect(route('admin.staff.show', $employee));

        $employee->refresh();
        $this->assertSame('new.staff@example.test', $employee->email);
        $this->assertNull($employee->email_verified_at);
        $this->assertNotSame('old-email-token', $employee->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'staff-email-session']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'old.staff@example.test']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'staff.email_changed',
            'subject_id' => $employee->id,
            'reason' => 'تحديث البريد الرسمي للموظف',
        ]);
    }

    public function test_staff_email_change_rejects_self_wrong_password_duplicate_and_terminated_target(): void
    {
        $manager = $this->staffWithPermission('roles.manage', 'guarded-email-manager');
        $employee = User::factory()->create();
        $employee->assignRole('delivery-worker');
        $other = User::factory()->create(['email' => 'occupied@example.test']);

        $this->actingAs($manager)->patch(route('admin.staff.email.update', $manager), [
            'email' => 'self-change@example.test',
            'current_password' => 'password',
            'reason' => 'لا يسمح بتغيير النفس',
        ])->assertForbidden();
        $this->patch(route('admin.staff.email.update', $employee), [
            'email' => $other->email,
            'current_password' => 'password',
            'reason' => 'يجب رفض البريد المستخدم',
        ])->assertSessionHasErrors('email');
        $this->patch(route('admin.staff.email.update', $employee), [
            'email' => 'available@example.test',
            'current_password' => 'wrong-password',
            'reason' => 'يجب رفض كلمة المرور',
        ])->assertSessionHasErrors('current_password')
            ->assertSessionMissing('_old_input.current_password')
            ->assertSessionMissing('_old_input.reason')
            ->assertSessionMissing('_old_input.email');

        $this->travel(61)->seconds();
        $employee->forceFill(['account_status' => AccountStatus::Terminated])->save();
        $this->patch(route('admin.staff.email.update', $employee), [
            'email' => 'available@example.test',
            'current_password' => 'password',
            'reason' => 'لا يغير بريد المنتهي',
        ])->assertSessionHasErrors('staff');
        $this->assertNotSame('available@example.test', $employee->fresh()->email);
    }

    public function test_staff_detail_shows_effective_permission_sources_and_rejects_non_staff_targets(): void
    {
        $manager = $this->staffWithPermission('roles.manage', 'directory-owner');
        $employee = $this->staffWithPermission('roles.manage', 'operations-lead', 'Operations Lead');
        $secondRole = Role::create(['name' => 'Support Backup', 'slug' => 'support-backup']);
        $secondRole->permissions()->attach(Permission::whereIn('slug', ['roles.manage', 'contact-messages.view'])->pluck('id'));
        $employee->assignRole($secondRole);
        DB::table('sessions')->insert([
            ['id' => 'employee-active', 'user_id' => $employee->id, 'ip_address' => '192.0.2.10', 'user_agent' => 'PRIVATE DEVICE', 'payload' => 'PRIVATE PAYLOAD', 'last_activity' => now()->timestamp],
            ['id' => 'employee-expired', 'user_id' => $employee->id, 'ip_address' => '192.0.2.11', 'user_agent' => 'OLD PRIVATE DEVICE', 'payload' => 'OLD PRIVATE PAYLOAD', 'last_activity' => now()->subDay()->timestamp],
        ]);
        config(['session.driver' => 'database']);

        $response = $this->actingAs($manager)->get(route('admin.staff.show', $employee))->assertOk()
            ->assertSee('Operations Lead')->assertSee('operations-lead')
            ->assertSee('Support Backup')->assertSee('roles.manage')
            ->assertSee('contact-messages.view')
            ->assertSee('الجلسات النشطة')->assertSee('آخر نشاط')->assertSee('UTC')
            ->assertDontSee('192.0.2.10')->assertDontSee('PRIVATE DEVICE')->assertDontSee('PRIVATE PAYLOAD')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $response->assertViewHas('effectivePermissions', function ($permissions) {
            $rolesManagement = $permissions->firstWhere('slug', 'roles.manage');
            $this->assertCount(2, $permissions);
            $this->assertNotNull($rolesManagement);
            $this->assertEqualsCanonicalizing(
                ['operations-lead', 'Support Backup'],
                $rolesManagement['sources']->all(),
            );

            return true;
        });
        $response->assertViewHas('sessionSummary', fn ($summary) => $summary['active_count'] === 1
            && $summary['last_active_at'] instanceof Carbon);

        $customer = User::factory()->create();
        $customer->assignRole('customer');
        $this->get(route('admin.staff.show', $customer))->assertNotFound();

        $unauthorized = User::factory()->create();
        $unauthorized->assignRole('delivery-worker');
        $this->actingAs($unauthorized)->get(route('admin.staff.show', $employee))->assertForbidden();
    }

    public function test_staff_activity_and_permission_history_are_minimized_before_reaching_view(): void
    {
        $manager = $this->staffWithPermission('roles.manage', 'timeline-manager');
        $employee = $this->staffWithPermission('contact-messages.view', 'timeline-worker', 'Timeline Worker');
        AuditLog::create([
            'actor_id' => $employee->id,
            'action' => 'support.case_reviewed',
            'subject_type' => User::class,
            'subject_id' => 987,
            'before' => ['secret' => 'PRIVATE BEFORE'],
            'after' => ['secret' => 'PRIVATE AFTER'],
            'metadata' => ['secret' => 'PRIVATE METADATA'],
            'created_at' => now(),
        ]);
        AuditLog::create([
            'actor_id' => $manager->id,
            'action' => 'staff.direct_permissions_changed',
            'subject_type' => $employee->getMorphClass(),
            'subject_id' => $employee->id,
            'before' => ['permissions' => ['payments.verify']],
            'after' => ['permissions' => ['contact-messages.view']],
            'reason' => 'إعادة توزيع الصلاحيات',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($manager)->get(route('admin.staff.show', $employee))
            ->assertOk()->assertSee('support.case_reviewed')->assertSee('#987')
            ->assertSee('إضافة صلاحية: contact-messages.view')
            ->assertSee('إزالة صلاحية: payments.verify')
            ->assertDontSee('PRIVATE BEFORE')->assertDontSee('PRIVATE AFTER')->assertDontSee('PRIVATE METADATA');
        $response->assertViewHas('activityEvents', function ($events) {
            $event = $events->firstWhere('action', 'support.case_reviewed');
            $this->assertNotNull($event);
            $this->assertEqualsCanonicalizing(
                ['id', 'action', 'subject_type', 'subject_id', 'created_at'],
                array_keys($event->getAttributes()),
            );

            return true;
        });
        $response->assertViewHas('permissionHistory', function ($events) {
            $event = $events->firstWhere('action', 'staff.direct_permissions_changed');
            $this->assertNotNull($event);
            $this->assertArrayNotHasKey('before', $event);
            $this->assertArrayNotHasKey('after', $event);

            return true;
        });
    }

    public function test_authorized_manager_revokes_only_target_sessions_and_action_is_audited(): void
    {
        config(['session.driver' => 'database']);
        $manager = $this->staffWithPermission('roles.manage', 'security-manager');
        $employee = $this->staffWithPermission('contact-messages.view', 'support-worker');
        $other = $this->staffWithPermission('contact-messages.view', 'other-worker');
        $employee->forceFill(['remember_token' => 'old-remember-token'])->save();
        DB::table('sessions')->insert([
            ['id' => 'target-one', 'user_id' => $employee->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 'target-two', 'user_id' => $employee->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 'other-session', 'user_id' => $other->id, 'payload' => '', 'last_activity' => time()],
        ]);

        $this->actingAs($manager)->post(route('admin.staff.sessions.revoke', $employee), [
            'current_password' => 'password',
            'reason' => 'جهاز الموظف مفقود',
        ])->assertRedirect(route('admin.staff.show', $employee));

        $this->assertDatabaseMissing('sessions', ['id' => 'target-one']);
        $this->assertDatabaseMissing('sessions', ['id' => 'target-two']);
        $this->assertDatabaseHas('sessions', ['id' => 'other-session']);
        $this->assertNotSame('old-remember-token', $employee->fresh()->remember_token);
        $audit = AuditLog::where('action', 'staff.sessions_revoked')->sole();
        $this->assertSame($manager->id, $audit->actor_id);
        $this->assertSame($employee->id, $audit->subject_id);
        $this->assertSame(['stored_sessions' => 2], $audit->before);
        $this->assertSame(['stored_sessions' => 0, 'remember_token_rotated' => true], $audit->after);
    }

    public function test_session_revocation_rejects_wrong_password_self_nonstaff_and_direct_unauthorized_calls(): void
    {
        config(['session.driver' => 'database']);
        $manager = $this->staffWithPermission('roles.manage', 'session-manager');
        $employee = $this->staffWithPermission('contact-messages.view', 'session-worker');
        DB::table('sessions')->insert(['id' => 'preserved-session', 'user_id' => $employee->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($manager)->post(route('admin.staff.sessions.revoke', $employee), [
            'current_password' => 'wrong-password', 'reason' => 'فحص كلمة مرور خاطئة',
        ])->assertSessionHasErrors('current_password')
            ->assertSessionMissing('_old_input.current_password')
            ->assertSessionMissing('_old_input.reason');
        $this->assertDatabaseHas('sessions', ['id' => 'preserved-session']);
        $this->post(route('admin.staff.sessions.revoke', $manager), [
            'current_password' => 'password', 'reason' => 'لا يسمح للنفس',
        ])->assertForbidden();

        $customer = User::factory()->create();
        $customer->assignRole('customer');
        $this->post(route('admin.staff.sessions.revoke', $customer), [
            'current_password' => 'password', 'reason' => 'ليس موظفًا تشغيليًا',
        ])->assertNotFound();

        $unauthorized = User::factory()->create();
        $unauthorized->assignRole('delivery-worker');
        $this->expectException(HttpException::class);
        app(StaffSecurityService::class)->revokeSessions($unauthorized, $employee->id, 'direct call');
    }

    public function test_manager_can_suspend_staff_and_revoke_existing_access_atomically(): void
    {
        config(['session.driver' => 'database']);
        $manager = $this->staffWithPermission('roles.manage', 'status-manager');
        $employee = $this->staffWithPermission('contact-messages.view', 'status-worker');
        $employee->forceFill(['remember_token' => 'old-token'])->save();
        DB::table('sessions')->insert([
            'id' => 'suspended-session', 'user_id' => $employee->id, 'payload' => '', 'last_activity' => time(),
        ]);

        $this->actingAs($manager)->patch(route('admin.staff.status.update', $employee), [
            'status' => AccountStatus::Suspended->value,
            'current_password' => 'password',
            'reason' => 'إيقاف مؤقت للتحقيق الأمني',
        ])->assertRedirect(route('admin.staff.show', $employee));

        $employee->refresh();
        $this->assertSame(AccountStatus::Suspended, $employee->account_status);
        $this->assertSame($manager->id, $employee->account_status_changed_by);
        $this->assertNotNull($employee->account_status_changed_at);
        $this->assertNotSame('old-token', $employee->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'suspended-session']);
        $audit = AuditLog::where('action', 'staff.account_status_changed')->sole();
        $this->assertSame(['account_status' => 'active'], $audit->before);
        $this->assertSame([
            'account_status' => 'suspended',
            'stored_sessions_revoked' => 1,
            'remember_token_rotated' => true,
        ], $audit->after);

        $response = $this->get(route('admin.staff.show', $employee))
            ->assertOk()
            ->assertSee('تغيير حالة الحساب')
            ->assertSee('إيقاف مؤقت للتحقيق الأمني')
            ->assertSee($manager->name);
        $response->assertViewHas('securityEvents', function ($events) {
            $this->assertCount(1, $events);
            $this->assertEqualsCanonicalizing(
                ['id', 'actor_id', 'action', 'reason', 'created_at'],
                array_keys($events->sole()->getAttributes()),
            );

            return true;
        });
    }

    public function test_suspended_account_cannot_log_in_or_continue_using_a_loaded_session(): void
    {
        $employee = $this->staffWithPermission('contact-messages.view', 'blocked-worker');
        $employee->forceFill(['account_status' => AccountStatus::Suspended])->save();

        $this->post(route('login.post'), [
            'login' => $employee->email,
            'password' => 'password',
        ])->assertSessionHasErrors('login');
        $this->assertGuest();

        $this->actingAs($employee)->get(route('home'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('login');
        $this->assertGuest();

        $this->actingAs($employee)->get(route('my-account'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_manager_can_reactivate_suspended_staff_but_cannot_change_self_or_terminal_states(): void
    {
        $manager = $this->staffWithPermission('roles.manage', 'reactivation-manager');
        $employee = $this->staffWithPermission('contact-messages.view', 'reactivation-worker');
        $employee->forceFill(['account_status' => AccountStatus::Suspended])->save();

        $this->actingAs($manager)->patch(route('admin.staff.status.update', $employee), [
            'status' => AccountStatus::Active->value,
            'current_password' => 'password',
            'reason' => 'انتهاء سبب التعليق المؤقت',
        ])->assertRedirect(route('admin.staff.show', $employee));
        $this->assertSame(AccountStatus::Active, $employee->fresh()->account_status);

        $this->patch(route('admin.staff.status.update', $manager), [
            'status' => AccountStatus::Suspended->value,
            'current_password' => 'password',
            'reason' => 'محاولة تغيير الحساب نفسه',
        ])->assertForbidden();

        $employee->forceFill(['account_status' => AccountStatus::Terminated])->save();
        $this->patch(route('admin.staff.status.update', $employee), [
            'status' => AccountStatus::Active->value,
            'current_password' => 'password',
            'reason' => 'لا يعاد من هذا المسار',
        ])->assertSessionHasErrors('status');
        $this->assertSame(AccountStatus::Terminated, $employee->fresh()->account_status);
    }

    public function test_status_change_cannot_suspend_the_last_active_admin(): void
    {
        $manager = $this->staffWithPermission('roles.manage', 'continuity-manager');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($manager)->patch(route('admin.staff.status.update', $admin), [
            'status' => AccountStatus::Suspended->value,
            'current_password' => 'password',
            'reason' => 'يجب حماية استمرارية الإدارة',
        ])->assertSessionHasErrors('status');

        $this->assertSame(AccountStatus::Active, $admin->fresh()->account_status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_manager_can_place_staff_on_leave_and_reactivate_them(): void
    {
        config(['session.driver' => 'database']);
        $manager = $this->staffWithPermission('roles.manage', 'leave-manager');
        $employee = $this->staffWithPermission('contact-messages.view', 'leave-worker');
        DB::table('sessions')->insert([
            'id' => 'leave-session', 'user_id' => $employee->id, 'payload' => '', 'last_activity' => time(),
        ]);

        $this->actingAs($manager)->patch(route('admin.staff.status.update', $employee), [
            'status' => AccountStatus::OnLeave->value,
            'current_password' => 'password',
            'reason' => 'إجازة مؤقتة معتمدة',
        ])->assertRedirect(route('admin.staff.show', $employee));
        $employee->refresh();
        $this->assertSame(AccountStatus::OnLeave, $employee->account_status);
        $this->assertDatabaseMissing('sessions', ['id' => 'leave-session']);

        $this->actingAs($employee)->get(route('home'))->assertRedirect(route('login'));
        $this->actingAs($manager)->patch(route('admin.staff.status.update', $employee), [
            'status' => AccountStatus::Active->value,
            'current_password' => 'password',
            'reason' => 'عودة الموظف من الإجازة',
        ])->assertRedirect(route('admin.staff.show', $employee));
        $this->assertSame(AccountStatus::Active, $employee->fresh()->account_status);
    }

    public function test_status_change_rejects_invalid_credentials_targets_and_direct_unauthorized_calls(): void
    {
        $manager = $this->staffWithPermission('roles.manage', 'protected-status-manager');
        $employee = $this->staffWithPermission('contact-messages.view', 'protected-status-worker');

        $this->actingAs($manager)->patch(route('admin.staff.status.update', $employee), [
            'status' => AccountStatus::Suspended->value,
            'current_password' => 'wrong-password',
            'reason' => 'يجب رفض كلمة المرور الخاطئة',
        ])->assertSessionHasErrors('current_password')
            ->assertSessionMissing('_old_input.current_password')
            ->assertSessionMissing('_old_input.reason');
        $this->assertSame(AccountStatus::Active, $employee->fresh()->account_status);

        $this->patch(route('admin.staff.status.update', $employee), [
            'status' => AccountStatus::Terminated->value,
            'current_password' => 'password',
            'reason' => 'الحالة ليست ضمن هذا المسار',
        ])->assertSessionHasErrors('status');
        $this->assertSame(AccountStatus::Active, $employee->fresh()->account_status);

        $customer = User::factory()->create();
        $customer->assignRole('customer');
        $this->patch(route('admin.staff.status.update', $customer), [
            'status' => AccountStatus::Suspended->value,
            'current_password' => 'password',
            'reason' => 'ليس حساب موظف تشغيلي',
        ])->assertNotFound();

        $unauthorized = User::factory()->create();
        $unauthorized->assignRole('delivery-worker');
        $this->expectException(HttpException::class);
        app(StaffSecurityService::class)->changeStatus(
            $unauthorized,
            $employee->id,
            AccountStatus::Suspended,
            'direct call',
        );
    }

    public function test_manager_updates_operational_roles_preserves_identity_and_revokes_sessions(): void
    {
        config(['session.driver' => 'database']);
        $manager = $this->staffWithPermission('roles.manage', 'role-manager');
        $employee = User::factory()->create(['remember_token' => 'role-token']);
        $employee->assignRole('customer');
        $employee->assignRole('delivery-worker');
        DB::table('sessions')->insert([
            'id' => 'role-change-session', 'user_id' => $employee->id, 'payload' => '', 'last_activity' => time(),
        ]);
        $adminRole = Role::where('slug', 'admin')->sole();

        $this->actingAs($manager)->patch(route('admin.staff.roles.update', $employee), [
            'role_ids' => [$adminRole->id],
            'current_password' => 'password',
            'reason' => 'نقل الموظف إلى إدارة المنصة',
        ])->assertRedirect(route('admin.staff.show', $employee));

        $employee->refresh();
        $this->assertTrue($employee->hasRole('customer'));
        $this->assertTrue($employee->hasRole('admin'));
        $this->assertFalse($employee->hasRole('delivery-worker'));
        $this->assertTrue($employee->hasPermission('payments.verify'));
        $this->assertDatabaseMissing('sessions', ['id' => 'role-change-session']);
        $this->assertNotSame('role-token', $employee->remember_token);
        $audit = AuditLog::where('action', 'staff.operational_roles_changed')->sole();
        $this->assertSame(['roles' => ['delivery-worker']], $audit->before);
        $this->assertSame([
            'roles' => ['admin'],
            'stored_sessions_revoked' => 1,
            'remember_token_rotated' => true,
        ], $audit->after);
    }

    public function test_role_change_protects_last_active_admin_and_rejects_invalid_role_sets(): void
    {
        $manager = $this->staffWithPermission('roles.manage', 'guarded-role-manager');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $deliveryRole = Role::where('slug', 'delivery-worker')->sole();
        $customerRole = Role::where('slug', 'customer')->sole();

        $this->actingAs($manager)->patch(route('admin.staff.roles.update', $admin), [
            'role_ids' => [$deliveryRole->id],
            'current_password' => 'password',
            'reason' => 'يجب إبقاء أدمن نشط',
        ])->assertSessionHasErrors('role_ids');
        $this->assertTrue($admin->fresh()->hasRole('admin'));

        $this->patch(route('admin.staff.roles.update', $admin), [
            'role_ids' => [$customerRole->id],
            'current_password' => 'password',
            'reason' => 'دور هوية غير تشغيلي',
        ])->assertSessionHasErrors('role_ids');
        $this->assertTrue($admin->fresh()->hasRole('admin'));
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_role_change_requires_reauthentication_and_blocks_self_or_unauthorized_service_calls(): void
    {
        $manager = $this->staffWithPermission('roles.manage', 'reauth-role-manager');
        $employee = User::factory()->create();
        $employee->assignRole('delivery-worker');
        $adminRole = Role::where('slug', 'admin')->sole();

        $this->actingAs($manager)->patch(route('admin.staff.roles.update', $employee), [
            'role_ids' => [$adminRole->id],
            'current_password' => 'wrong-password',
            'reason' => 'رفض كلمة المرور الخاطئة',
        ])->assertSessionHasErrors('current_password')
            ->assertSessionMissing('_old_input.current_password')
            ->assertSessionMissing('_old_input.reason');
        $this->assertTrue($employee->fresh()->hasRole('delivery-worker'));

        $this->patch(route('admin.staff.roles.update', $manager), [
            'role_ids' => [$adminRole->id],
            'current_password' => 'password',
            'reason' => 'لا يسمح بتعديل النفس',
        ])->assertForbidden();

        $unauthorized = User::factory()->create();
        $unauthorized->assignRole('delivery-worker');
        $this->expectException(HttpException::class);
        app(StaffSecurityService::class)->updateOperationalRoles(
            $unauthorized,
            $employee->id,
            [$adminRole->id],
            'direct call',
        );
    }

    public function test_manager_grants_and_revokes_direct_permissions_with_immediate_effect_and_audit(): void
    {
        config(['session.driver' => 'database']);
        $manager = $this->staffWithPermission('roles.manage', 'permission-manager');
        $employee = User::factory()->create(['remember_token' => 'permission-token']);
        $employee->assignRole('delivery-worker');
        $permission = Permission::where('slug', 'payments.verify')->sole();
        DB::table('sessions')->insert([
            'id' => 'permission-session', 'user_id' => $employee->id, 'payload' => '', 'last_activity' => time(),
        ]);

        $this->actingAs($manager)->patch(route('admin.staff.permissions.update', $employee), [
            'permission_ids' => [$permission->id],
            'current_password' => 'password',
            'reason' => 'تكليف مؤقت بمراجعة الدفعات',
        ])->assertRedirect(route('admin.staff.show', $employee));

        $employee->refresh();
        $this->assertTrue($employee->hasPermission('payments.verify'));
        $this->assertTrue($employee->directPermissions()->whereKey($permission->id)->exists());
        $this->assertDatabaseMissing('sessions', ['id' => 'permission-session']);
        $this->assertNotSame('permission-token', $employee->remember_token);
        $this->get(route('admin.staff.show', $employee))
            ->assertOk()->assertSee('payments.verify')->assertSee('مباشرة');

        $firstAudit = AuditLog::where('action', 'staff.direct_permissions_changed')->sole();
        $this->assertSame(['permissions' => []], $firstAudit->before);
        $this->assertSame(['payments.verify'], $firstAudit->after['permissions']);

        $this->patch(route('admin.staff.permissions.update', $employee), [
            'permission_ids' => [],
            'current_password' => 'password',
            'reason' => 'انتهاء التكليف المؤقت',
        ])->assertRedirect(route('admin.staff.show', $employee));
        $this->assertFalse($employee->fresh()->hasPermission('payments.verify'));
        $this->assertCount(2, AuditLog::where('action', 'staff.direct_permissions_changed')->get());
    }

    public function test_removing_direct_copy_does_not_revoke_permission_inherited_from_role(): void
    {
        $manager = $this->staffWithPermission('roles.manage', 'inherited-permission-manager');
        $employee = User::factory()->create();
        $employee->assignRole('delivery-worker');
        $permission = Permission::where('slug', 'deliveries.accept')->sole();
        $employee->directPermissions()->attach($permission->id);

        $this->actingAs($manager)->patch(route('admin.staff.permissions.update', $employee), [
            'permission_ids' => [],
            'current_password' => 'password',
            'reason' => 'إزالة النسخة المباشرة المكررة',
        ])->assertRedirect(route('admin.staff.show', $employee));

        $this->assertFalse($employee->fresh()->directPermissions()->whereKey($permission->id)->exists());
        $this->assertTrue($employee->fresh()->hasPermission('deliveries.accept'));
    }

    public function test_direct_permission_change_requires_password_and_rejects_self_or_unauthorized_service_calls(): void
    {
        $manager = $this->staffWithPermission('roles.manage', 'protected-permission-manager');
        $employee = User::factory()->create();
        $employee->assignRole('delivery-worker');
        $permission = Permission::where('slug', 'payments.verify')->sole();

        $this->actingAs($manager)->patch(route('admin.staff.permissions.update', $employee), [
            'permission_ids' => [$permission->id],
            'current_password' => 'wrong-password',
            'reason' => 'رفض كلمة المرور الخاطئة',
        ])->assertSessionHasErrors('current_password')
            ->assertSessionMissing('_old_input.current_password')
            ->assertSessionMissing('_old_input.reason');
        $this->assertFalse($employee->hasPermission('payments.verify'));

        $this->patch(route('admin.staff.permissions.update', $manager), [
            'permission_ids' => [$permission->id],
            'current_password' => 'password',
            'reason' => 'لا يسمح بتعديل النفس',
        ])->assertForbidden();

        $unauthorized = User::factory()->create();
        $unauthorized->assignRole('delivery-worker');
        $this->expectException(HttpException::class);
        app(StaffSecurityService::class)->updateDirectPermissions(
            $unauthorized,
            $employee->id,
            [$permission->id],
            'direct call',
        );
    }

    public function test_manager_invites_staff_and_single_use_password_setup_activates_the_account(): void
    {
        config([
            'password_recovery.mail_enabled' => true,
            'password_recovery.url' => 'https://mart.example',
            'mail.default' => 'smtp',
        ]);
        Notification::fake();
        $manager = $this->staffWithPermission('roles.manage', 'invitation-manager');
        $role = Role::where('slug', 'delivery-worker')->sole();
        $permission = Permission::where('slug', 'contact-messages.view')->sole();
        $rawToken = null;

        $this->actingAs($manager)->get(route('admin.staff.create'))
            ->assertOk()->assertSee('دعوة موظف جديد')->assertSee('delivery-worker');
        $this->post(route('admin.staff.store'), [
            'name' => '  Invited Employee  ',
            'email' => ' INVITED@EXAMPLE.TEST ',
            'role_ids' => [$role->id],
            'permission_ids' => [$permission->id],
            'current_password' => 'password',
            'reason' => 'تعيين موظف توصيل جديد',
        ])->assertRedirect();

        $employee = User::where('email', 'invited@example.test')->sole();
        $this->assertSame('Invited Employee', $employee->name);
        $this->assertSame(AccountStatus::Invited, $employee->account_status);
        $this->assertNull($employee->password_changed_at);
        $this->assertTrue($employee->hasRole('delivery-worker'));
        $this->assertTrue($employee->directPermissions()->whereKey($permission->id)->exists());
        Notification::assertSentTo($employee, StaffInvitation::class, function ($notification) use ($employee, &$rawToken) {
            $rawToken = $notification->token;
            $storedToken = DB::table('password_reset_tokens')->where('email', $employee->email)->value('token');
            $this->assertNotSame($rawToken, $storedToken);
            $this->assertTrue(Hash::check($rawToken, $storedToken));
            $this->assertStringStartsWith('https://mart.example/reset-password/', $notification->toMail($employee)->actionUrl);

            return true;
        });
        $this->assertNotNull($rawToken);
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.invited', 'subject_id' => $employee->id]);

        $this->post(route('logout'));
        $this->post(route('login.post'), ['login' => $employee->email, 'password' => 'anything'])
            ->assertSessionHasErrors('login');
        $this->get(route('password.reset', ['token' => $rawToken, 'email' => $employee->email]))
            ->assertOk()->assertSee('فعّل دعوة الموظف');
        $this->post(route('password.update'), [
            'token' => $rawToken,
            'email' => $employee->email,
            'password' => 'StrongPassword123',
            'password_confirmation' => 'StrongPassword123',
        ])->assertRedirect(route('login'));

        $employee->refresh();
        $this->assertSame(AccountStatus::Active, $employee->account_status);
        $this->assertNotNull($employee->password_changed_at);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $employee->email]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.invitation_accepted', 'subject_id' => $employee->id]);
        $this->post(route('password.update'), [
            'token' => $rawToken,
            'email' => $employee->email,
            'password' => 'AnotherPassword123',
            'password_confirmation' => 'AnotherPassword123',
        ])->assertRedirect(route('password.request'));
        $this->post(route('login.post'), [
            'login' => $employee->email,
            'password' => 'StrongPassword123',
        ])->assertRedirect();
        $this->assertAuthenticatedAs($employee->fresh());
    }

    public function test_invitation_requires_ready_mail_reauthentication_and_an_operational_role(): void
    {
        Notification::fake();
        $manager = $this->staffWithPermission('roles.manage', 'guarded-invitation-manager');
        $deliveryRole = Role::where('slug', 'delivery-worker')->sole();
        $customerRole = Role::where('slug', 'customer')->sole();
        $before = User::count();

        config(['password_recovery.mail_enabled' => false, 'mail.default' => 'log']);
        $this->actingAs($manager)->get(route('admin.staff.create'))
            ->assertOk()->assertSee('البريد غير جاهز')->assertDontSee('إنشاء الحساب وإرسال الدعوة');
        $this->post(route('admin.staff.store'), [
            'name' => 'Blocked Invite', 'email' => 'blocked@example.test',
            'role_ids' => [$deliveryRole->id], 'permission_ids' => [],
            'current_password' => 'password', 'reason' => 'يجب رفض البريد غير الجاهز',
        ])->assertSessionHasErrors('email');
        $this->assertSame($before, User::count());

        config([
            'password_recovery.mail_enabled' => true,
            'password_recovery.url' => 'https://mart.example',
            'mail.default' => 'smtp',
        ]);
        $this->post(route('admin.staff.store'), [
            'name' => 'Invalid Role Invite', 'email' => 'invalid-role@example.test',
            'role_ids' => [$customerRole->id], 'permission_ids' => [],
            'current_password' => 'password', 'reason' => 'يجب رفض دور العميل',
        ])->assertSessionHasErrors('role_ids');
        $this->post(route('admin.staff.store'), [
            'name' => 'Wrong Password Invite', 'email' => 'wrong-password@example.test',
            'role_ids' => [$deliveryRole->id], 'permission_ids' => [],
            'current_password' => 'wrong-password', 'reason' => 'يجب رفض كلمة المرور',
        ])->assertSessionHasErrors('current_password')
            ->assertSessionMissing('_old_input.current_password')
            ->assertSessionMissing('_old_input.reason')
            ->assertSessionMissing('_old_input.email');
        $this->assertSame($before, User::count());
        Notification::assertNothingSent();
    }

    public function test_manager_resends_only_pending_invitation_and_invalidates_the_previous_token(): void
    {
        config([
            'password_recovery.mail_enabled' => true,
            'password_recovery.url' => 'https://mart.example',
            'mail.default' => 'smtp',
        ]);
        Notification::fake();
        $manager = $this->staffWithPermission('roles.manage', 'resend-manager');
        $employee = User::factory()->create(['account_status' => AccountStatus::Invited]);
        $employee->assignRole('delivery-worker');
        $oldToken = Password::broker('users')->createToken($employee);
        $newToken = null;

        $this->actingAs($manager)->get(route('admin.staff.show', $employee))
            ->assertOk()->assertSee('إعادة إرسال الدعوة');
        $this->post(route('admin.staff.invitation.resend', $employee), [
            'current_password' => 'password',
            'reason' => 'الموظف لم يستلم الرسالة الأولى',
        ])->assertRedirect(route('admin.staff.show', $employee));

        Notification::assertSentTo($employee, StaffInvitation::class, function ($notification) use (&$newToken) {
            $newToken = $notification->token;

            return true;
        });
        $stored = DB::table('password_reset_tokens')->where('email', $employee->email)->value('token');
        $this->assertNotNull($newToken);
        $this->assertNotSame($oldToken, $newToken);
        $this->assertFalse(Hash::check($oldToken, $stored));
        $this->assertTrue(Hash::check($newToken, $stored));
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.invitation_resent', 'subject_id' => $employee->id]);

        $employee->forceFill(['account_status' => AccountStatus::Active])->save();
        $this->post(route('admin.staff.invitation.resend', $employee), [
            'current_password' => 'password',
            'reason' => 'الحساب النشط لا يعاد دعوته',
        ])->assertNotFound();
    }

    public function test_invitation_delivery_failure_keeps_pending_account_but_removes_the_unusable_token(): void
    {
        config([
            'password_recovery.mail_enabled' => true,
            'password_recovery.url' => 'https://mart.example',
            'mail.default' => 'smtp',
        ]);
        $this->mock(Dispatcher::class, function (MockInterface $mock) {
            $mock->shouldReceive('send')->once()->andThrow(new RuntimeException('simulated transport failure'));
        });
        $manager = $this->staffWithPermission('roles.manage', 'failed-delivery-manager');
        $role = Role::where('slug', 'delivery-worker')->sole();

        $this->actingAs($manager)->post(route('admin.staff.store'), [
            'name' => 'Pending Delivery',
            'email' => 'pending-delivery@example.test',
            'role_ids' => [$role->id],
            'permission_ids' => [],
            'current_password' => 'password',
            'reason' => 'اختبار فشل ناقل البريد',
        ])->assertRedirect()->assertSessionHasErrors('email');

        $employee = User::where('email', 'pending-delivery@example.test')->sole();
        $this->assertSame(AccountStatus::Invited, $employee->account_status);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $employee->email]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.invited', 'subject_id' => $employee->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.invitation_delivery_failed', 'subject_id' => $employee->id]);
    }

    public function test_manager_terminates_idle_staff_and_preserves_historical_roles(): void
    {
        config(['session.driver' => 'database']);
        $manager = $this->staffWithPermission('roles.manage', 'termination-manager');
        $employee = User::factory()->create(['remember_token' => 'termination-token']);
        $employee->assignRole('delivery-worker');
        $resetToken = Password::broker('users')->createToken($employee);
        $this->assertNotNull($resetToken);
        DB::table('sessions')->insert([
            'id' => 'termination-session', 'user_id' => $employee->id, 'payload' => '', 'last_activity' => time(),
        ]);

        $this->actingAs($manager)->post(route('admin.staff.terminate', $employee), [
            'confirmation' => strtoupper($employee->email),
            'current_password' => 'password',
            'reason' => 'انتهاء علاقة العمل رسميًا',
        ])->assertRedirect(route('admin.staff.show', $employee));

        $employee->refresh();
        $this->assertSame(AccountStatus::Terminated, $employee->account_status);
        $this->assertTrue($employee->hasRole('delivery-worker'));
        $this->assertFalse($employee->hasPermission('deliveries.accept'));
        $this->assertNotSame('termination-token', $employee->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'termination-session']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $employee->email]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.terminated', 'subject_id' => $employee->id]);
        $this->actingAs($employee)->get(route('home'))->assertRedirect(route('login'));

        $adminRole = Role::where('slug', 'admin')->sole();
        $this->actingAs($manager)->patch(route('admin.staff.roles.update', $employee), [
            'role_ids' => [$adminRole->id],
            'current_password' => 'password',
            'reason' => 'لا تعدل أدوار المنتهي',
        ])->assertSessionHasErrors('staff');
        $this->assertTrue($employee->fresh()->hasRole('delivery-worker'));
    }

    public function test_termination_requires_exact_confirmation_and_preserves_the_last_active_admin(): void
    {
        $manager = $this->staffWithPermission('roles.manage', 'guarded-termination-manager');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($manager)->post(route('admin.staff.terminate', $admin), [
            'confirmation' => 'other@example.test',
            'current_password' => 'password',
            'reason' => 'تأكيد غير مطابق يجب رفضه',
        ])->assertSessionHasErrors('confirmation');
        $this->assertSame(AccountStatus::Active, $admin->fresh()->account_status);

        $this->post(route('admin.staff.terminate', $admin), [
            'confirmation' => $admin->email,
            'current_password' => 'password',
            'reason' => 'لا يمكن إنهاء آخر أدمن',
        ])->assertSessionHasErrors('staff');
        $this->assertSame(AccountStatus::Active, $admin->fresh()->account_status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_termination_moves_open_work_to_department_queue_without_history_loss(): void
    {
        $manager = $this->staffWithPermission('roles.manage', 'queue-termination-manager');
        $department = StaffDepartment::create(['name' => 'Operations Queue', 'slug' => 'operations-queue', 'is_active' => true]);
        $employee = User::factory()->create(['staff_department_id' => $department->id]);
        $employee->assignRole('delivery-worker');
        $task = StaffTask::create([
            'title' => 'Preserve queued task', 'task_type' => 'general', 'priority' => 'normal',
            'assigned_to' => $employee->id, 'assigned_by' => $manager->id, 'status' => 'in_progress', 'version' => 0,
        ]);

        $this->actingAs($manager)->post(route('admin.staff.terminate', $employee), [
            'tasks_destination' => 'department', 'confirmation' => $employee->email,
            'current_password' => 'password', 'reason' => 'Transfer remaining work to department queue',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertNull($task->assigned_to);
        $this->assertSame($department->id, $task->queue_department_id);
        $this->assertSame('assigned', $task->status->value);
        $this->assertSame(AccountStatus::Terminated, $employee->fresh()->account_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.task_reassigned_on_termination', 'subject_id' => $task->id]);
    }

    public function test_successful_login_updates_minimized_staff_login_summary(): void
    {
        $manager = $this->staffWithPermission('roles.manage', 'login-summary-manager');
        $employee = $this->staffWithPermission('contact-messages.view', 'login-summary-worker');
        $employee->forceFill(['password_changed_at' => '2026-09-01 12:34:56'])->save();

        $this->post(route('logout'));
        $this->post(route('login.post'), [
            'login' => $employee->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('login');
        $employee->refresh();
        $this->assertSame(0, $employee->login_count);
        $this->assertSame(1, $employee->failed_login_count);
        $this->assertNotNull($employee->last_failed_login_at);

        $this->post(route('login.post'), [
            'login' => $employee->email,
            'password' => 'password',
        ])->assertRedirect();
        $employee->refresh();
        $this->assertSame(1, $employee->login_count);
        $this->assertSame(0, $employee->failed_login_count);
        $this->assertNotNull($employee->last_login_at);
        $this->assertArrayNotHasKey('login_count', $employee->toArray());
        $this->assertArrayNotHasKey('last_login_at', $employee->toArray());
        $this->assertArrayNotHasKey('failed_login_count', $employee->toArray());
        $this->assertArrayNotHasKey('last_failed_login_at', $employee->toArray());
        $this->assertArrayNotHasKey('password_changed_at', $employee->toArray());

        $this->post(route('logout'));
        $this->actingAs($manager)->get(route('admin.staff.show', $employee))
            ->assertOk()->assertSee('المصادقة الثنائية (2FA)')->assertSee('غير متاحة بعد')
            ->assertSee('آخر تغيير لكلمة المرور')->assertSee('2026-09-01 12:34:56')
            ->assertSee('آخر تسجيل دخول')->assertSee('عدد مرات الدخول')
            ->assertSee('محاولات فاشلة منذ آخر دخول ناجح')->assertSee('آخر محاولة فاشلة')->assertSee('UTC');
    }

    public function test_bulk_role_change_requires_confirmation_and_rejects_sensitive_roles_atomically(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $first = User::factory()->create();
        $second = User::factory()->create();
        $first->assignRole('delivery-worker');
        $second->assignRole('delivery-worker');
        $routine = Role::create(['name' => 'Support routine', 'slug' => 'support-routine']);
        $routine->permissions()->attach(Permission::where('slug', 'contact-messages.manage')->sole());

        $payload = [
            'staff_ids' => [$first->id, $second->id], 'role_ids' => [$routine->id],
            'confirmation' => 'BULK ROLE CHANGE', 'current_password' => 'password',
            'reason' => 'Move trained staff to the support queue',
        ];
        $this->actingAs($admin)->patch(route('admin.staff.roles.bulk-update'), $payload)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($first->fresh()->hasRole('support-routine'));
        $this->assertTrue($second->fresh()->hasRole('support-routine'));
        $this->assertSame(2, AuditLog::where('action', 'staff.operational_roles_changed')->count());

        $payload['role_ids'] = [Role::where('slug', 'admin')->sole()->id];
        $this->patch(route('admin.staff.roles.bulk-update'), $payload)->assertSessionHasErrors('role_ids');
        $this->assertFalse($first->fresh()->hasRole('admin'));
        $this->assertFalse($second->fresh()->hasRole('admin'));
    }

    private function staffWithPermission(string $permission, string $roleSlug, string $name = 'Staff Manager'): User
    {
        $role = Role::create(['name' => $roleSlug, 'slug' => $roleSlug]);
        $role->permissions()->attach(Permission::where('slug', $permission)->sole());
        $user = User::factory()->create(['name' => $name]);
        $user->assignRole($role);

        return $user;
    }
}

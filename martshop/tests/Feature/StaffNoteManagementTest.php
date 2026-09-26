<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StaffNote;
use App\Models\User;
use App\Services\StaffNoteService;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class StaffNoteManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
    }

    public function test_admin_adds_and_views_escaped_append_only_staff_note(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $employee = User::factory()->create();
        $employee->assignRole('delivery-worker');

        $this->actingAs($admin)->post(route('admin.staff.notes.store', $employee), [
            'body' => '<script>PRIVATE NOTE</script>',
            'current_password' => 'password',
        ])->assertRedirect(route('admin.staff.show', $employee));

        $note = StaffNote::sole();
        $this->assertSame($admin->id, $note->author_id);
        $this->assertSame($employee->id, $note->staff_id);
        $this->assertArrayNotHasKey('body', $note->toArray());
        $this->get(route('admin.staff.show', $employee))->assertOk()
            ->assertSee('&lt;script&gt;PRIVATE NOTE&lt;/script&gt;', false)
            ->assertDontSee('<script>PRIVATE NOTE</script>', false);

        $audit = AuditLog::where('action', 'staff.note_added')->sole();
        $this->assertSame(['note_id' => $note->id], $audit->after);
        $this->assertStringNotContainsString('PRIVATE NOTE', json_encode($audit->toArray()));

        $this->expectException(LogicException::class);
        $note->update(['body' => 'changed']);
    }

    public function test_role_manager_without_note_permission_neither_loads_nor_adds_notes(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $manager = $this->staffWithPermission('roles.manage', 'limited-role-manager');
        $employee = User::factory()->create();
        $employee->assignRole('delivery-worker');
        StaffNote::create([
            'staff_id' => $employee->id,
            'author_id' => $admin->id,
            'body' => 'RESTRICTED NOTE BODY',
        ]);

        $this->actingAs($manager)->get(route('admin.staff.show', $employee))
            ->assertOk()->assertDontSee('ملاحظات الموظف الداخلية')->assertDontSee('RESTRICTED NOTE BODY');
        $this->post(route('admin.staff.notes.store', $employee), [
            'body' => 'Unauthorized note',
            'current_password' => 'password',
        ])->assertForbidden();
        $this->assertDatabaseCount('staff_notes', 1);

        $this->expectException(HttpException::class);
        app(StaffNoteService::class)->add($manager, $employee->id, 'Direct unauthorized note');
    }

    public function test_note_creation_requires_current_password_and_operational_target(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $employee = User::factory()->create();
        $employee->assignRole('delivery-worker');
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $this->actingAs($admin)->post(route('admin.staff.notes.store', $employee), [
            'body' => 'Should not be stored',
            'current_password' => 'wrong-password',
        ])->assertSessionHasErrors('current_password')
            ->assertSessionMissing('_old_input.current_password');
        $this->assertDatabaseCount('staff_notes', 0);

        $this->post(route('admin.staff.notes.store', $customer), [
            'body' => 'Customer is not staff',
            'current_password' => 'password',
        ])->assertNotFound();
        $this->assertDatabaseCount('staff_notes', 0);
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

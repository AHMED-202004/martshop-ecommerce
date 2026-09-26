<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\Location;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffScopeScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
    }

    public function test_manager_saves_audited_location_scope_and_complete_week_schedule(): void
    {
        $manager = $this->manager();
        $employee = User::factory()->create();
        $employee->assignRole('delivery-worker');
        $gaza = Location::create(['name' => 'غزة', 'slug' => 'gaza', 'type' => 'city', 'is_active' => true]);
        $rafah = Location::create(['name' => 'رفح', 'slug' => 'rafah', 'type' => 'city', 'is_active' => true]);
        $schedule = $this->weekSchedule();
        $schedule[1] = ['day_of_week' => 1, 'is_working' => '1', 'starts_at' => '09:00', 'ends_at' => '17:00'];

        $this->actingAs($manager)->patch(route('admin.staff.scope-schedule.update', $employee), [
            'location_ids' => [$rafah->id, $gaza->id],
            'schedule' => $schedule,
            'timezone' => 'Asia/Hebron',
            'current_password' => 'password',
            'reason' => 'تحديد نطاق ودوام الموظف',
        ])->assertRedirect(route('admin.staff.show', $employee));

        $this->assertEqualsCanonicalizing([$gaza->id, $rafah->id], $employee->staffLocations()->pluck('locations.id')->all());
        $this->assertSame(7, $employee->staffWorkSchedules()->count());
        $monday = $employee->staffWorkSchedules()->where('day_of_week', 1)->sole();
        $this->assertTrue($monday->is_working);
        $this->assertSame('09:00', substr($monday->starts_at, 0, 5));
        $this->assertSame('17:00', substr($monday->ends_at, 0, 5));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'staff.scope_schedule_updated',
            'subject_id' => $employee->id,
            'reason' => 'تحديد نطاق ودوام الموظف',
        ]);
        $this->get(route('admin.staff.show', $employee))
            ->assertOk()->assertSee('غزة')->assertSee('رفح')->assertSee('09:00');
    }

    public function test_invalid_schedule_or_inactive_location_is_rejected_atomically(): void
    {
        $manager = $this->manager();
        $employee = User::factory()->create();
        $employee->assignRole('delivery-worker');
        $inactive = Location::create(['name' => 'معطّل', 'slug' => 'inactive', 'is_active' => false]);
        $schedule = $this->weekSchedule();
        $schedule[2] = ['day_of_week' => 2, 'is_working' => '1', 'starts_at' => '18:00', 'ends_at' => '08:00'];

        $this->actingAs($manager)->patch(route('admin.staff.scope-schedule.update', $employee), [
            'location_ids' => [],
            'schedule' => $schedule,
            'timezone' => 'Asia/Hebron',
            'current_password' => 'password',
            'reason' => 'رفض وقت غير منطقي',
        ])->assertSessionHasErrors('schedule');
        $this->assertDatabaseCount('staff_work_schedules', 0);

        $this->patch(route('admin.staff.scope-schedule.update', $employee), [
            'location_ids' => [$inactive->id],
            'schedule' => $this->weekSchedule(),
            'timezone' => 'Asia/Hebron',
            'current_password' => 'password',
            'reason' => 'رفض موقع معطّل',
        ])->assertSessionHasErrors('location_ids.0');
        $this->assertDatabaseCount('staff_location', 0);
        $this->assertDatabaseCount('staff_work_schedules', 0);
    }

    public function test_scope_schedule_rejects_wrong_password_and_terminated_staff(): void
    {
        $manager = $this->manager();
        $employee = User::factory()->create();
        $employee->assignRole('delivery-worker');
        $payload = [
            'location_ids' => [],
            'schedule' => $this->weekSchedule(),
            'timezone' => 'Asia/Hebron',
            'current_password' => 'wrong-password',
            'reason' => 'رفض كلمة المرور الخاطئة',
        ];

        $this->actingAs($manager)->patch(route('admin.staff.scope-schedule.update', $employee), $payload)
            ->assertSessionHasErrors('current_password')
            ->assertSessionMissing('_old_input.current_password')
            ->assertSessionMissing('_old_input.reason');
        $employee->forceFill(['account_status' => AccountStatus::Terminated])->save();
        $payload['current_password'] = 'password';
        $this->patch(route('admin.staff.scope-schedule.update', $employee), $payload)
            ->assertSessionHasErrors('staff');
        $this->assertDatabaseCount('staff_work_schedules', 0);
    }

    /** @return list<array{day_of_week: int, is_working: string, starts_at: string|null, ends_at: string|null}> */
    private function weekSchedule(): array
    {
        return array_map(fn (int $day) => [
            'day_of_week' => $day,
            'is_working' => '0',
            'starts_at' => null,
            'ends_at' => null,
        ], range(0, 6));
    }

    private function manager(): User
    {
        $role = Role::create(['name' => 'Scope manager', 'slug' => 'scope-manager']);
        $role->permissions()->attach(Permission::where('slug', 'roles.manage')->sole());
        $manager = User::factory()->create();
        $manager->assignRole($role);

        return $manager;
    }
}

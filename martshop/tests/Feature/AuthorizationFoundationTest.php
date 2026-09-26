<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AuthorizationFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
    }

    public function test_user_can_be_assigned_a_system_role(): void
    {
        $user = User::factory()->create();
        $user->assignRole('merchant');

        $this->assertTrue($user->hasRole('merchant'));
        $this->assertFalse($user->hasRole('admin'));
    }

    public function test_gate_uses_database_permissions_and_respects_least_privilege(): void
    {
        $merchant = User::factory()->create();
        $merchant->assignRole('merchant');

        $moderator = User::factory()->create();
        $moderatorRole = Role::create(['name' => 'Product Moderator', 'slug' => 'product-moderator']);
        $moderatorRole->permissions()->attach(Permission::where('slug', 'products.moderate')->firstOrFail());
        $moderator->assignRole($moderatorRole);

        $this->assertFalse(Gate::forUser($merchant)->allows('products.moderate'));
        $this->assertTrue(Gate::forUser($moderator)->allows('products.moderate'));
        $this->assertFalse(Gate::forUser($moderator)->allows('withdrawals.approve'));
    }

    public function test_admin_role_receives_the_seeded_permissions(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->assertTrue($admin->hasPermission('payments.verify'));
        $this->assertTrue($admin->hasPermission('roles.manage'));
    }

    public function test_non_active_accounts_have_no_effective_permissions(): void
    {
        foreach ([AccountStatus::Invited, AccountStatus::OnLeave, AccountStatus::Suspended, AccountStatus::Terminated] as $status) {
            $user = User::factory()->create(['account_status' => $status]);
            $user->assignRole('admin');
            $this->assertFalse($user->hasPermission('roles.manage'), $status->value);
            $this->assertFalse(Gate::forUser($user)->allows('roles.manage'), $status->value);
        }
    }
}

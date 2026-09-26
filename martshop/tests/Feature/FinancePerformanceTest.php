<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\FinancePerformanceService;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancePerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
    }

    public function test_finance_metrics_keep_decisions_and_discrepancies_separate(): void
    {
        $manager = $this->staff('roles.manage', 'finance-performance-manager');
        $reviewer = $this->staff('payments.verify', 'finance-performance-reviewer');
        foreach ([
            ['accepted', 120, 90], ['rejected', 100, 70], ['suspicious', 80, 50],
            ['short_amount', 60, 30], ['overpaid', 40, 10], ['duplicate', 30, 5],
        ] as $index => [$status, $createdMinutes, $reviewedMinutes]) {
            $payment = Payment::create([
                'order_no' => 'FIN-'.$index, 'amount' => 10000, 'currency' => 'ILS',
                'provider' => 'manual', 'provider_ref' => 'FIN-REF-'.$index, 'status' => $status,
                'reviewed_by' => $reviewer->id, 'reviewed_at' => now()->subMinutes($reviewedMinutes),
            ]);
            $payment->forceFill(['created_at' => now()->subMinutes($createdMinutes)])->saveQuietly();
        }
        Payment::create(['order_no' => 'FIN-PENDING', 'amount' => 10000, 'currency' => 'ILS', 'provider' => 'manual', 'provider_ref' => 'FIN-PENDING', 'status' => 'pending']);

        $summary = app(FinancePerformanceService::class)->summary($manager, $reviewer, 'week');
        $this->assertSame(6, $summary['reviewed']);
        $this->assertSame(1, $summary['accepted']);
        $this->assertSame(1, $summary['rejected']);
        $this->assertSame(1, $summary['escalated']);
        $this->assertSame(2, $summary['corrections']);
        $this->assertSame(3, $summary['reconciliation_discrepancies']);
        $this->assertSame(29.2, $summary['average_review_minutes']);
        $this->assertSame(1, $summary['pending_workload']);

        $this->actingAs($manager)->get(route('admin.staff.show', ['staff' => $reviewer]))
            ->assertOk()->assertSee('أداء مراجعة الدفعات')->assertSee('صحة المبلغ والطلب والإثبات لها الأولوية');
    }

    private function staff(string $permission, string $roleSlug): User
    {
        $role = Role::create(['name' => $roleSlug, 'slug' => $roleSlug]);
        $role->permissions()->attach(Permission::where('slug', $permission)->sole());
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}

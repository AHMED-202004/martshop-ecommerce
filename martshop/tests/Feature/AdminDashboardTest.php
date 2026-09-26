<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\StaffTaskPriority;
use App\Enums\StaffTaskStatus;
use App\Models\MarketplaceSetting;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StaffTask;
use App\Models\User;
use App\Services\AdminDashboardService;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
    }

    public function test_admin_dashboard_shows_existing_sections_and_private_operational_status(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $response = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('لوحة إدارة Mart.ps')->assertSee('طلبات السحب الجديدة: معطّلة')
            ->assertSee('التحرير التلقائي للتسويات: معطّلة')->assertSee('لا توجد أعمال ضمن الفئات أعلاه حاليًا.')
            ->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString("default-src 'none'", $response->headers->get('Content-Security-Policy'));
        $response->assertViewHas('sections', function ($sections) {
            $this->assertCount(17, $sections);
            foreach ($sections as $section) {
                $this->assertTrue(Route::has($section['route']));
                if ($section['key'] === 'staff') {
                    $this->assertSame([1, 0, 0], array_values($section['metrics']));

                    continue;
                }
                foreach ($section['metrics'] as $count) {
                    $this->assertSame(0, $count);
                }
            }

            return true;
        });
        $this->get(route('my-account'))->assertOk()->assertSee(route('admin.dashboard'), false);
    }

    public function test_guests_customers_merchants_and_delivery_workers_cannot_access_admin_dashboard(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        foreach (['customer', 'merchant', 'delivery-worker'] as $slug) {
            $user = User::factory()->create();
            $user->assignRole($slug);
            $this->assertFalse(app(AdminDashboardService::class)->canAccess($user));
            $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
            $this->get(route('my-account'))->assertOk()->assertDontSee(route('admin.dashboard'), false);
        }
    }

    public function test_payment_reviewer_sees_only_pending_count_without_private_records_or_writes(): void
    {
        [$staff] = $this->staffWith('payments.verify');
        foreach (['pending', 'pending', 'accepted', 'rejected'] as $index => $status) {
            Payment::create(['order_no' => 'DASH-'.$index, 'amount' => 10000, 'currency' => 'ILS', 'status' => $status,
                'provider' => 'manual', 'sender_name' => 'Private Sender', 'sender_account' => 'SECRET-ACCOUNT']);
        }
        $before = DB::table('payments')->orderBy('id')->get()->toJson();
        $auditCount = DB::table('audit_logs')->count();
        DB::enableQueryLog();
        $response = $this->actingAs($staff)->get(route('admin.dashboard'))->assertOk()
            ->assertDontSee('SECRET-ACCOUNT')->assertDontSee('Private Sender')
            ->assertDontSee(route('admin.withdrawals.index'), false)->assertDontSee('حالة التشغيل');
        $queries = collect(DB::getQueryLog())->pluck('query')->implode("\n");
        DB::disableQueryLog();
        foreach (['refund_requests', 'refund_destinations', 'refund_transfers', 'withdrawal_requests', 'ledger_entries', 'merchants'] as $table) {
            $this->assertStringNotContainsString('from "'.$table.'"', $queries);
        }
        $response->assertViewHas('sections', fn ($sections) => count($sections) === 1
            && $sections[0]['key'] === 'payments' && array_values($sections[0]['metrics']) === [2]);
        $this->assertSame($before, DB::table('payments')->orderBy('id')->get()->toJson());
        $this->assertSame($auditCount, DB::table('audit_logs')->count());
        $this->get(route('admin.withdrawals.index'))->assertForbidden();
    }

    public function test_withdrawal_settings_permission_does_not_expose_withdrawal_queue_counts(): void
    {
        [$staff] = $this->staffWith('withdrawals.settings');
        DB::enableQueryLog();
        $response = $this->actingAs($staff)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('سياسة السحب')->assertDontSee('سحوبات بانتظار القرار')->assertDontSee('وسائل سحب بانتظار التحقق');
        $queries = collect(DB::getQueryLog())->pluck('query')->implode("\n");
        DB::disableQueryLog();
        $this->assertStringNotContainsString('from "withdrawal_requests"', $queries);
        $this->assertStringNotContainsString('from "merchant_payout_methods"', $queries);
        $response->assertViewHas('sections', fn ($sections) => count($sections) === 1 && $sections[0]['metrics'] === []);
        MarketplaceSetting::updateOrCreate(['key' => 'withdrawals.enabled'], ['value' => '1']);
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('طلبات السحب الجديدة: مفعّلة');
        $this->assertSame('1', MarketplaceSetting::where('key', 'withdrawals.enabled')->value('value'));
    }

    public function test_dashboard_permission_changes_are_not_cached_between_requests(): void
    {
        [$staff, $role] = $this->staffWith('payments.verify');
        $this->actingAs($staff)->get(route('admin.dashboard'))->assertOk()->assertSee('مراجعة الدفعات');
        $role->permissions()->detach();
        $this->assertFalse(app(AdminDashboardService::class)->canAccess($staff));
        $this->get(route('admin.dashboard'))->assertForbidden();
        $role->permissions()->attach(Permission::where('slug', 'settings.manage')->sole());
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('إعدادات الموقع')->assertDontSee('دفعات بانتظار القرار')
            ->assertViewHas('sections', fn ($sections) => count($sections) === 1 && $sections[0]['key'] === 'settings');
    }

    public function test_direct_permission_appears_in_dashboard_and_navigation_immediately(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('customer');
        $permission = Permission::where('slug', 'roles.manage')->sole();
        $staff->directPermissions()->attach($permission);

        $this->assertTrue(app(AdminDashboardService::class)->canAccess($staff));
        $this->assertSame(
            [['title' => 'إدارة الموظفين', 'route' => 'admin.staff.index']],
            app(AdminDashboardService::class)->navigation($staff),
        );
        $this->actingAs($staff)->get(route('admin.dashboard'))
            ->assertOk()->assertSee('إدارة الموظفين')->assertDontSee('مراجعة الدفعات');

        $staff->directPermissions()->detach($permission);
        $this->assertFalse(app(AdminDashboardService::class)->canAccess($staff));
        $this->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_staff_dashboard_metrics_count_only_active_accounts_and_open_overdue_tasks(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $worker = User::factory()->create();
        $worker->assignRole('delivery-worker');
        $suspended = User::factory()->create(['account_status' => AccountStatus::Suspended]);
        $suspended->assignRole('delivery-worker');
        StaffTask::create([
            'title' => 'Open overdue task',
            'task_type' => 'general',
            'priority' => StaffTaskPriority::High,
            'assigned_to' => $worker->id,
            'assigned_by' => $admin->id,
            'status' => StaffTaskStatus::Assigned,
            'due_at' => now()->subMinute(),
        ]);
        StaffTask::create([
            'title' => 'Completed task',
            'task_type' => 'general',
            'priority' => StaffTaskPriority::Normal,
            'assigned_to' => $worker->id,
            'assigned_by' => $admin->id,
            'status' => StaffTaskStatus::Completed,
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $response->assertViewHas('sections', function ($sections) {
            $metrics = collect($sections)->firstWhere('key', 'staff')['metrics'];
            $this->assertSame(2, $metrics['حسابات موظفين نشطة']);
            $this->assertSame(1, $metrics['مهام موظفين مفتوحة']);
            $this->assertSame(1, $metrics['مهام موظفين متأخرة']);

            return true;
        });
    }

    public function test_direct_dashboard_service_rejects_user_without_supported_section_permission(): void
    {
        [$staff] = $this->staffWith('refunds.cancel');
        $this->assertFalse(app(AdminDashboardService::class)->canAccess($staff));
        $this->expectException(HttpException::class);
        app(AdminDashboardService::class)->data($staff);
    }

    public function test_header_groups_all_authorized_routes_in_one_closed_native_disclosure(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $html = $this->actingAs($admin)->get(route('my-account'))->assertOk()->getContent();
        $xpath = $this->htmlXPath($html);
        $this->assertSame(1, $xpath->query('//details[@class="admin-menu"]')->length);
        $this->assertSame(0, $xpath->query('//details[@class="admin-menu"]/@open')->length);
        $this->assertSame('أقسام الإدارة', trim($xpath->query('//details[@class="admin-menu"]/summary')->item(0)->textContent));
        $links = $xpath->query('//details[@class="admin-menu"]/nav/a');
        $this->assertSame(16, $links->length);
        $hrefs = [];
        foreach ($links as $link) {
            $hrefs[] = $link->getAttribute('href');
        }
        $this->assertCount(16, array_unique($hrefs));
        foreach (app(AdminDashboardService::class)->data($admin)['sections'] as $section) {
            $this->assertContains(route($section['route']), $hrefs);
        }
        $dashboardLinks = $xpath->query('//div[@class="top-actions"]/a[@href="'.route('admin.dashboard').'"]');
        $this->assertSame(1, $dashboardLinks->length, 'Dashboard remains directly accessible outside the collapsed menu');
    }

    public function test_header_navigation_queries_only_permissions_and_observes_revocation(): void
    {
        [$staff, $role] = $this->staffWith('withdrawals.settings');
        DB::enableQueryLog();
        $links = app(AdminDashboardService::class)->navigation($staff);
        $queries = collect(DB::getQueryLog())->pluck('query')->implode("\n");
        DB::disableQueryLog();
        $this->assertSame([['title' => 'سياسة السحب', 'route' => 'admin.withdrawals.index']], $links);
        foreach (['payments', 'refund_requests', 'withdrawal_requests', 'merchant_payout_methods', 'ledger_entries'] as $table) {
            $this->assertStringNotContainsString('from "'.$table.'"', $queries);
        }
        $html = $this->actingAs($staff)->get(route('my-account'))->assertOk()->getContent();
        $this->assertSame(1, $this->htmlXPath($html)->query('//details[@class="admin-menu"]/nav/a')->length);
        $role->permissions()->detach();
        $this->assertSame([], app(AdminDashboardService::class)->navigation($staff));
        $this->get(route('my-account'))->assertOk()->assertDontSee('class="admin-menu"', false);
    }

    public function test_header_menu_marks_current_page_and_is_absent_for_guests(): void
    {
        $this->get(route('login'))->assertOk()->assertDontSee('class="admin-menu"', false);
        [$staff] = $this->staffWith('payments.verify');
        $html = $this->actingAs($staff)->get(route('admin.payments.index'))->assertOk()->getContent();
        $active = $this->htmlXPath($html)->query('//details[@class="admin-menu"]/nav/a[@aria-current="page"]');
        $this->assertSame(1, $active->length);
        $this->assertSame(route('admin.payments.index'), $active->item(0)->getAttribute('href'));
        $this->assertSame(1, $this->htmlXPath($html)->query('//details[@class="admin-menu"]/nav/a')->length);
    }

    private function htmlXPath(string $html): \DOMXPath
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new \DOMDocument;
            $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);

            return new \DOMXPath($document);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function staffWith(string $permission): array
    {
        $role = Role::create(['name' => 'Scoped dashboard staff', 'slug' => 'dashboard-staff']);
        $role->permissions()->attach(Permission::where('slug', $permission)->sole());
        $staff = User::factory()->create();
        $staff->assignRole($role);

        return [$staff, $role];
    }
}

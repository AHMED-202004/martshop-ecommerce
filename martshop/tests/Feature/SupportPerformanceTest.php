<?php

namespace Tests\Feature;

use App\Enums\SupportTicketStatus;
use App\Models\ContactMessage;
use App\Models\ContactMessageReply;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\SupportPerformanceService;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SupportPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
    }

    public function test_support_performance_uses_real_ticket_and_reply_data_in_selected_period(): void
    {
        $manager = $this->staffWithPermission('roles.manage', 'support-performance-manager');
        $worker = $this->staffWithPermission('contact-messages.manage', 'support-agent');
        $ticket = ContactMessage::create([
            'topic' => 'تأخر طلب', 'contact' => 'customer@example.test', 'message' => 'تفاصيل خاصة',
            'status' => SupportTicketStatus::Closed, 'assigned_to' => $worker->id,
            'assigned_at' => now()->subMinutes(120), 'first_response_at' => now()->subMinutes(90),
            'resolved_at' => now()->subMinutes(30), 'resolved_by' => $worker->id,
            'closed_at' => now()->subMinutes(20), 'closed_by' => $worker->id,
            'sla_due_at' => now()->subMinutes(100), 'reopened_count' => 1,
        ]);
        $ticket->forceFill(['created_at' => now()->subMinutes(120)])->saveQuietly();
        ContactMessageReply::create([
            'contact_message_id' => $ticket->id, 'author_id' => $worker->id,
            'body' => 'رد خاص لا يجب عرضه', 'is_internal' => false,
        ]);
        ContactMessageReply::create([
            'contact_message_id' => $ticket->id, 'author_id' => $worker->id,
            'body' => 'ملاحظة داخلية', 'is_internal' => true,
        ]);

        $summary = app(SupportPerformanceService::class)->summary($manager, $worker, 'week');
        $this->assertSame(1, $summary['assigned']);
        $this->assertSame(1, $summary['handled']);
        $this->assertSame(1, $summary['public_replies']);
        $this->assertSame(1, $summary['resolved']);
        $this->assertSame(1, $summary['closed']);
        $this->assertSame(1, $summary['reopened']);
        $this->assertSame(1, $summary['sla_breaches']);
        $this->assertSame(30.0, $summary['average_first_response_minutes']);
        $this->assertSame(90.0, $summary['average_resolution_minutes']);

        $this->actingAs($manager)->get(route('admin.staff.show', [
            'staff' => $worker, 'performance_period' => 'week',
        ]))->assertOk()->assertSee('أداء الدعم')->assertSee('غير متاح — لا تُجمع تقييمات بعد')->assertDontSee('رد خاص لا يجب عرضه');
    }

    public function test_support_performance_rejects_unauthorized_actor(): void
    {
        $worker = $this->staffWithPermission('contact-messages.manage', 'support-agent-two');

        $this->expectException(HttpException::class);
        app(SupportPerformanceService::class)->summary($worker, $worker, 'week');
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

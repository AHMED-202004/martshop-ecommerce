<?php

namespace Tests\Feature;

use App\Enums\SupportTicketStatus;
use App\Models\ContactMessage;
use App\Models\ContactMessageReply;
use App\Models\Permission;
use App\Models\User;
use App\Services\SupportTicketService;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SupportTicketFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
    }

    public function test_contact_messages_have_non_destructive_ticket_defaults_and_relations(): void
    {
        $customer = User::factory()->create();
        $agent = User::factory()->create();
        $ticket = ContactMessage::query()->create([
            'user_id' => $customer->id,
            'topic' => 'الدعم الفني',
            'contact' => 'customer@example.test',
            'message' => 'أحتاج مساعدة في الطلب.',
        ])->refresh();

        $this->assertSame(SupportTicketStatus::Open, $ticket->status);
        $this->assertSame(0, $ticket->reopened_count);
        $this->assertSame(0, $ticket->lock_version);
        $this->assertNull($ticket->assigned_to);
        $this->assertNull($ticket->first_response_at);

        $reply = $ticket->replies()->create([
            'author_id' => $agent->id,
            'body' => 'رد خاص بالعميل',
            'is_internal' => false,
        ]);

        $this->assertTrue($reply->contactMessage->is($ticket));
        $this->assertTrue($reply->author->is($agent));
        $this->assertArrayNotHasKey('body', $reply->toArray());
        $this->assertDatabaseHas('contact_message_replies', [
            'contact_message_id' => $ticket->id,
            'author_id' => $agent->id,
            'is_internal' => false,
        ]);
    }

    public function test_support_management_permission_is_separate_and_schema_is_indexed(): void
    {
        $viewer = User::factory()->create();
        $viewer->directPermissions()->attach(Permission::where('slug', 'contact-messages.view')->sole());
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->assertTrue($viewer->hasPermission('contact-messages.view'));
        $this->assertFalse($viewer->hasPermission('contact-messages.manage'));
        $this->assertTrue($admin->hasPermission('contact-messages.manage'));
        $this->assertTrue(Schema::hasColumns('contact_messages', [
            'status', 'assigned_to', 'assigned_at', 'first_response_at', 'resolved_at',
            'closed_at', 'sla_due_at', 'reopened_count', 'lock_version',
        ]));
        $this->assertTrue(Schema::hasTable('contact_message_replies'));
    }

    public function test_support_service_assigns_replies_and_records_first_response_without_auditing_body(): void
    {
        $agent = User::factory()->create();
        $agent->directPermissions()->attach(Permission::where('slug', 'contact-messages.manage')->sole());
        $ticket = ContactMessage::query()->create([
            'topic' => 'طلب مساعدة', 'contact' => 'private@example.test', 'message' => 'محتوى خاص',
        ]);
        $service = app(SupportTicketService::class);

        $this->actingAs($agent);
        $service->assign($agent, $ticket->id, $agent->id, 0);
        $reply = $service->reply($agent, $ticket->id, 'نص رد سري', false, 1);
        $ticket->refresh();

        $this->assertSame(SupportTicketStatus::InProgress, $ticket->status);
        $this->assertSame($agent->id, $ticket->assigned_to);
        $this->assertNotNull($ticket->first_response_at);
        $this->assertSame(2, $ticket->lock_version);
        $this->assertSame($agent->id, $reply->author_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'support.ticket_assigned', 'subject_id' => $ticket->id]);
        $audit = $this->assertDatabaseHas('audit_logs', ['action' => 'support.ticket_replied', 'subject_id' => $ticket->id]);
        $serializedAudits = json_encode(\App\Models\AuditLog::query()->get()->toArray());
        $this->assertStringNotContainsString('نص رد سري', $serializedAudits);
        $this->assertStringNotContainsString('محتوى خاص', $serializedAudits);
    }

    public function test_support_service_rejects_stale_or_cross_agent_work_and_controls_reopening(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $permission = Permission::where('slug', 'contact-messages.manage')->sole();
        $first->directPermissions()->attach($permission);
        $second->directPermissions()->attach($permission);
        $ticket = ContactMessage::query()->create([
            'topic' => 'مشكلة طلب', 'contact' => 'private@example.test', 'message' => 'خاص',
        ]);
        $service = app(SupportTicketService::class);

        $this->actingAs($first);
        $service->assign($first, $ticket->id, $first->id, 0);
        try {
            $service->reply($second, $ticket->id, 'رد موظف آخر', false, 1);
            $this->fail('Cross-agent reply should fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ticket', $exception->errors());
        }
        try {
            $service->transition($first, $ticket->id, SupportTicketStatus::Resolved, 0);
            $this->fail('Stale transition should fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ticket', $exception->errors());
        }

        $service->transition($first, $ticket->id, SupportTicketStatus::InProgress, 1);
        $service->transition($first, $ticket->id, SupportTicketStatus::Resolved, 2);
        $service->transition($first, $ticket->id, SupportTicketStatus::Closed, 3);
        $service->transition($first, $ticket->id, SupportTicketStatus::InProgress, 4);
        $ticket->refresh();

        $this->assertSame(SupportTicketStatus::InProgress, $ticket->status);
        $this->assertSame(1, $ticket->reopened_count);
        $this->assertNull($ticket->resolved_at);
        $this->assertNull($ticket->closed_at);
        $this->assertSame(5, $ticket->lock_version);
    }
}

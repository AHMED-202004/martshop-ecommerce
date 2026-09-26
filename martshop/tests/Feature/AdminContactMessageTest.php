<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\Permission;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminContactMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
    }

    public function test_contact_inbox_requires_its_permission_and_renders_private_escaped_messages(): void
    {
        $customer = User::factory()->create(['name' => 'عميل الدعم']);
        $message = ContactMessage::query()->create([
            'user_id' => $customer->id,
            'topic' => 'الدعم الفني',
            'contact' => 'private@example.test',
            'ref' => 'ORDER-42',
            'message' => '<script>alert("private")</script> تفاصيل المشكلة',
        ]);
        $ordinary = User::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($ordinary)->get(route('admin.contact-messages.index'))
            ->assertForbidden()
            ->assertDontSee('private@example.test');
        $this->actingAs($ordinary)->get(route('admin.contact-messages.show', $message))
            ->assertForbidden()
            ->assertDontSee('private@example.test');

        $response = $this->actingAs($admin)->get(route('admin.contact-messages.index'))
            ->assertOk()
            ->assertSee('عميل الدعم')
            ->assertSee(route('admin.contact-messages.show', $message), false)
            ->assertDontSee('private@example.test')
            ->assertDontSee('ORDER-42')
            ->assertDontSee('تفاصيل المشكلة')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame($message->id, $response->viewData('messages')->first()->id);
        $this->assertEqualsCanonicalizing(
            ['id', 'user_id', 'topic', 'created_at'],
            array_keys($response->viewData('messages')->first()->getAttributes()),
        );
        $this->assertDatabaseMissing('audit_logs', ['action' => 'contact-message.viewed']);

        $detail = $this->actingAs($admin)->get(route('admin.contact-messages.show', $message))
            ->assertOk()
            ->assertSee('private@example.test')
            ->assertSee('ORDER-42')
            ->assertSee('&lt;script&gt;alert(&quot;private&quot;)&lt;/script&gt; تفاصيل المشكلة', false)
            ->assertDontSee('<script>alert("private")</script>', false)
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $detail->headers->get('Cache-Control'));
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'contact-message.viewed',
            'subject_type' => ContactMessage::class,
            'subject_id' => $message->id,
        ]);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('رسائل العملاء')
            ->assertSee(route('admin.contact-messages.index'), false);
    }

    public function test_support_workflow_is_manage_permission_scoped_versioned_and_does_not_flash_reply_body(): void
    {
        $viewer = User::factory()->create();
        $viewer->directPermissions()->attach(Permission::where('slug', 'contact-messages.view')->sole());
        $agent = User::factory()->create(['name' => 'موظف الدعم']);
        $agent->directPermissions()->attach([
            Permission::where('slug', 'contact-messages.view')->sole()->id,
            Permission::where('slug', 'contact-messages.manage')->sole()->id,
        ]);
        $ticket = ContactMessage::query()->create([
            'topic' => 'مشكلة طلب', 'contact' => 'private@example.test', 'message' => 'رسالة خاصة',
        ]);

        $this->actingAs($viewer)->patch(route('admin.contact-messages.assign', $ticket), [
            'assigned_to' => $agent->id, 'expected_version' => 0,
        ])->assertForbidden();
        $this->assertNull($ticket->refresh()->assigned_to);

        $this->actingAs($agent)->get(route('admin.contact-messages.show', $ticket))
            ->assertOk()
            ->assertSee('إدارة التذكرة')
            ->assertSee('موظف الدعم');
        $this->patch(route('admin.contact-messages.assign', $ticket), [
            'assigned_to' => $agent->id, 'expected_version' => 0,
        ])->assertRedirect();
        $this->post(route('admin.contact-messages.reply', $ticket), [
            'body' => 'رد الدعم للعميل', 'expected_version' => 1,
        ])->assertRedirect();
        $this->patch(route('admin.contact-messages.transition', $ticket), [
            'status' => 'resolved', 'expected_version' => 2,
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertSame('resolved', $ticket->status->value);
        $this->assertNotNull($ticket->first_response_at);
        $this->assertDatabaseHas('contact_message_replies', [
            'contact_message_id' => $ticket->id,
            'author_id' => $agent->id,
            'body' => 'رد الدعم للعميل',
            'is_internal' => false,
        ]);
        $this->get(route('admin.contact-messages.show', $ticket))
            ->assertOk()
            ->assertSee('رد الدعم للعميل')
            ->assertSee('محلولة');

        $this->from(route('admin.contact-messages.show', $ticket))
            ->post(route('admin.contact-messages.reply', $ticket), [
                'body' => 'س', 'expected_version' => $ticket->lock_version,
            ])->assertRedirect(route('admin.contact-messages.show', $ticket))
            ->assertSessionHasErrors('body')
            ->assertSessionMissing('_old_input.body');
    }
}

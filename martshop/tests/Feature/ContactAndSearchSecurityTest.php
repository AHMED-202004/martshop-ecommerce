<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\ContactMessage;
use App\Models\MarketplaceSetting;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactAndSearchSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_page_is_private_and_validation_does_not_flash_personal_content(): void
    {
        $user = User::factory()->create();
        $page = $this->actingAs($user)
            ->get(route('contact.create'))
            ->assertOk()
            ->assertSee($user->email)
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString('no-store', $page->headers->get('Cache-Control'));

        $failed = $this->actingAs($user)
            ->from(route('contact.create'))
            ->post(route('contact.store'), [
                'topic' => 'UNAPPROVED PRIVATE TOPIC',
                'contact' => 'not-a-contact',
                'ref' => 'PRIVATE-ORDER-REFERENCE',
                'message' => 'no',
            ])
            ->assertRedirect(route('contact.create'))
            ->assertSessionHasErrors(['topic', 'contact', 'message']);
        foreach (['topic', 'contact', 'ref', 'message'] as $key) {
            $this->assertArrayNotHasKey($key, $failed->getSession()->getOldInput());
        }
        $this->assertDatabaseCount('contact_messages', 0);

        MarketplaceSetting::query()->create([
            'key' => 'support.first_response_sla_minutes', 'value' => '60',
            'type' => 'integer', 'group' => 'support',
        ]);
        $createdAt = now();
        $this->actingAs($user)->post(route('contact.store'), [
            'topic' => 'الدعم الفني',
            'contact' => ' private-contact@example.test ',
            'ref' => ' ORDER-42 ',
            'message' => ' رسالة دعم صالحة للاختبار ',
        ])->assertRedirect()->assertSessionHas('status');
        $message = ContactMessage::query()->firstOrFail();
        $this->assertSame('private-contact@example.test', $message->getRawOriginal('contact'));
        $this->assertSame('ORDER-42', $message->getRawOriginal('ref'));
        $this->assertSame('رسالة دعم صالحة للاختبار', $message->getRawOriginal('message'));
        $this->assertTrue($message->sla_due_at->between($createdAt->copy()->addMinutes(59), now()->addMinutes(61)));
        foreach (['contact', 'ref', 'message'] as $key) {
            $this->assertArrayNotHasKey($key, $message->toArray());
        }

        $this->actingAs($user)->post(route('contact.store'), [
            'topic' => 'خدمة الزبائن',
            'contact' => '+970 599 123 456',
            'message' => 'رسالة برقم هاتف صالح',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('contact_messages', 2);

        $page->assertSee('name="contact"', false)
            ->assertSee('for="contactTopic"', false)
            ->assertSee('aria-describedby="contactHelp"', false)
            ->assertSee('for="contactMessage"', false)
            ->assertSee('maxlength="190"', false)
            ->assertSee('minlength="5" maxlength="5000"', false);
    }

    public function test_search_treats_wildcards_literally_and_loads_only_public_card_fields(): void
    {
        Product::query()->create([
            'name' => 'Ordinary product',
            'slug' => 'ordinary-product',
            'price' => 20,
            'status' => ProductStatus::Active,
        ]);
        Product::query()->create([
            'name' => 'Literal 100% product',
            'slug' => 'literal-100-percent-product',
            'price' => 10,
            'status' => ProductStatus::Active,
        ]);

        $response = $this->get(route('search', ['q' => '%']))
            ->assertOk()
            ->assertSee('Literal 100% product')
            ->assertDontSee('Ordinary product');
        $product = $response->viewData('products')->getCollection()->firstOrFail();
        $this->assertEqualsCanonicalizing(
            ['id', 'brand_id', 'name', 'slug', 'image', 'price', 'sale_price'],
            array_keys($product->getAttributes()),
        );
        $response->assertSee(route('product.show', $product->slug), false);

        $this->get(route('search', ['q' => '<script>alert(1)</script>']))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);

        $this->from('/')->get(route('search', ['q' => str_repeat('x', 101)]))
            ->assertRedirect('/')
            ->assertSessionHasErrors('q');
    }
}

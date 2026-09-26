<?php

namespace Tests\Feature;

use App\Models\{Order, OrderItem, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerOrderHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_and_confirmation_require_authentication(): void
    {
        $owner = User::factory()->create();
        $order = $this->order($owner, 'Private item');
        $this->get(route('orders.history'))->assertRedirect(route('login'));
        $this->get(route('order.confirmation', $order))->assertRedirect(route('login'));
    }

    public function test_history_is_private_and_contains_only_the_owners_orders(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->order($owner, 'Owner visible item');
        $this->order($other, 'Other customer secret item');

        $response = $this->actingAs($owner)->get(route('orders.history'))->assertOk()
            ->assertSee('Owner visible item')->assertDontSee('Other customer secret item')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString("frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
    }

    public function test_history_paginates_fifteen_orders_without_duplicates(): void
    {
        $owner = User::factory()->create();
        foreach (range(1, 17) as $number) {
            $this->order($owner, sprintf('Owner item %02d', $number));
        }

        $first = $this->actingAs($owner)->get(route('orders.history'))->assertOk()
            ->assertSee('Owner item 17')->assertDontSee('Owner item 01')
            ->assertSee('صفحة 1 من 2');
        $first->assertViewHas('orders', fn ($orders) => $orders->count() === 15 && $orders->total() === 17);

        $second = $this->get(route('orders.history', ['page' => 2]))->assertOk()
            ->assertSee('Owner item 01')->assertDontSee('Owner item 17')
            ->assertSee('صفحة 2 من 2');
        $second->assertViewHas('orders', fn ($orders) => $orders->count() === 2 && $orders->total() === 17);
    }

    public function test_paginated_history_uses_bounded_reads_and_performs_no_business_writes(): void
    {
        $owner = User::factory()->create();
        foreach (range(1, 20) as $number) {
            $this->order($owner, 'Query item '.$number);
        }
        $before = DB::table('orders')->orderBy('id')->get()->toJson();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($owner)->get(route('orders.history'))->assertOk();
        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(20, $queries->filter(fn ($query) => str_starts_with(strtolower(ltrim($query['query'])), 'select'))->count());
        $this->assertSame(0, $queries->filter(fn ($query) => preg_match('/^(insert|update|delete)\b/i', ltrim($query['query'])))->count());
        $this->assertSame($before, DB::table('orders')->orderBy('id')->get()->toJson());
    }

    public function test_history_loads_only_fields_and_relationships_rendered_by_the_page(): void
    {
        $owner = User::factory()->create();
        $this->order($owner, 'Minimized history item');

        $response = $this->actingAs($owner)->get(route('orders.history'))->assertOk();
        $order = $response->viewData('orders')->getCollection()->sole();

        $this->assertEqualsCanonicalizing(
            ['id', 'user_id', 'total', 'currency', 'status', 'payment_status', 'created_at', 'merchant_orders_count'],
            array_keys($order->getAttributes()),
        );
        $this->assertEqualsCanonicalizing(
            ['id', 'order_id', 'product_name', 'price', 'qty', 'image'],
            array_keys($order->items->sole()->getAttributes()),
        );
        $this->assertFalse($order->relationLoaded('merchantOrders'));
        $this->assertFalse($order->relationLoaded('payments'));
        $this->assertTrue($order->relationLoaded('latestPayment'));
    }

    public function test_delivery_and_dispute_serialization_hides_private_snapshots_and_reasons(): void
    {
        $delivery = new \App\Models\Delivery([
            'origin_snapshot' => ['address' => 'Private merchant address'],
            'destination_snapshot' => ['address' => 'Private customer address'],
            'assignment_notes' => 'Private assignment note',
            'delivery_note' => 'Private delivery note',
            'confirmation_pin' => '123456',
            'confirmation_pin_hash' => 'private-hash',
            'assignment_key' => 'private-key',
        ]);
        $dispute = new \App\Models\DeliveryDispute([
            'reason' => 'Private dispute reason', 'close_reason' => 'Private closure reason',
        ]);

        foreach (['origin_snapshot', 'destination_snapshot', 'assignment_notes', 'delivery_note',
            'confirmation_pin', 'confirmation_pin_hash', 'assignment_key'] as $key) {
            $this->assertArrayNotHasKey($key, $delivery->toArray());
        }
        foreach (['reason', 'close_reason'] as $key) {
            $this->assertArrayNotHasKey($key, $dispute->toArray());
        }
    }

    public function test_owner_confirmation_has_private_headers_and_another_customer_is_denied(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $order = $this->order($owner, 'Confirmed owner item');
        $this->actingAs($owner)->get(route('order.confirmation', $order))->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Frame-Options', 'DENY');
        $this->actingAs($other)->get(route('order.confirmation', $order))->assertForbidden()
            ->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Frame-Options', 'DENY');
    }

    private function order(User $owner, string $itemName): Order
    {
        $order = Order::create([
            'user_id' => $owner->id,
            'total' => 25,
            'status' => 'pending',
            'payment_method' => 'cod',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_name' => $itemName,
            'price' => 25,
            'qty' => 1,
        ]);

        return $order;
    }
}

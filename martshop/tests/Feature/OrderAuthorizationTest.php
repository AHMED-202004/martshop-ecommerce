<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cannot_view_another_customers_order(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $order = Order::create(['user_id' => $owner->id, 'total' => 100, 'status' => 'pending', 'payment_method' => 'cod']);
        $this->actingAs($other)->get(route('order.confirmation', $order))->assertForbidden();
    }

    public function test_customer_can_view_their_own_order(): void
    {
        $owner = User::factory()->create();
        $order = Order::create(['user_id' => $owner->id, 'total' => 100, 'status' => 'pending', 'payment_method' => 'cod']);
        $this->actingAs($owner)->get(route('order.confirmation', $order))->assertOk();
    }
}

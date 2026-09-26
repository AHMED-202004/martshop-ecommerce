<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\MarketplaceSetting;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderPlacementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CheckoutBoundarySecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_card_endpoints_fail_with_private_responses(): void
    {
        $user = User::factory()->create();

        $form = $this->actingAs($user)->get(route('payment.card'))
            ->assertRedirect(route('cart.index'))
            ->assertSessionHasErrors('payment')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString('no-store', $form->headers->get('Cache-Control'));

        $charge = $this->actingAs($user)->post(route('payment.card.charge'), [
            'card_number' => '4111111111111111',
            'exp' => '12/30',
            'cvv' => '123',
        ])->assertStatus(503)
            ->assertDontSee('4111111111111111')
            ->assertDontSee('12/30')
            ->assertDontSee('123')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString('no-store', $charge->headers->get('Cache-Control'));
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_confirmation_page_loads_only_the_fields_it_renders(): void
    {
        $user = User::factory()->create();
        $order = Order::query()->create([
            'user_id' => $user->id,
            'subtotal' => 10,
            'shipping_fee' => 0,
            'total' => 10,
            'currency' => 'ILS',
            'delivery_address_snapshot' => ['address' => 'PRIVATE CHECKOUT ADDRESS'],
            'status' => OrderStatus::Confirmed,
            'payment_method' => 'cod',
            'checkout_token' => (string) Str::uuid(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('order.confirmation', $order))
            ->assertOk()
            ->assertDontSee('PRIVATE CHECKOUT ADDRESS')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $shown = $response->viewData('order');
        $this->assertEqualsCanonicalizing(
            ['id', 'user_id', 'status'],
            array_keys($shown->getAttributes()),
        );
        $this->assertSame([], $shown->getRelations());
        $this->assertArrayNotHasKey('delivery_address_snapshot', $order->toArray());
        $this->assertArrayNotHasKey('checkout_token', $order->toArray());
    }

    public function test_order_service_rechecks_checkout_boundary_rules(): void
    {
        $user = User::factory()->create();
        $service = app(OrderPlacementService::class);

        $this->assertThrows(
            fn () => $service->place($user, [], 'manual_transfer', 'not-a-uuid'),
            ValidationException::class,
        );
        $this->assertThrows(
            fn () => $service->place($user, [], 'card', (string) Str::uuid()),
            ValidationException::class,
        );
        $this->assertThrows(
            fn () => $service->place($user, array_fill(0, 101, []), 'manual_transfer', (string) Str::uuid()),
            ValidationException::class,
        );

        MarketplaceSetting::query()->create([
            'key' => 'site.orders_enabled',
            'value' => '0',
            'type' => 'boolean',
            'group' => 'features',
        ]);
        $this->assertThrows(
            fn () => $service->place($user, [], 'manual_transfer', (string) Str::uuid()),
            ValidationException::class,
        );
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('stock_reservations', 0);
    }

    public function test_cash_on_delivery_cannot_be_smuggled_through_http_or_the_order_service(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('checkout.confirm'), ['payment_method' => 'cod'])
            ->assertSessionHasErrors('payment_method');

        $this->assertThrows(
            fn () => app(OrderPlacementService::class)->place(
                $user,
                [],
                'cod',
                (string) Str::uuid(),
            ),
            ValidationException::class,
        );
        $this->assertDatabaseCount('orders', 0);
    }
}

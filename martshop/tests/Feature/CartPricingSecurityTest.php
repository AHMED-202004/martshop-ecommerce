<?php

namespace Tests\Feature;

use App\Models\{Category, MarketplaceSetting, Product};
use App\Models\User;
use App\Services\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CartPricingSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_ignores_client_supplied_product_details_and_price(): void
    {
        $product = Product::create([
            'name' => 'Server Product', 'slug' => 'server-product',
            'price' => 125, 'sale_price' => 95,
            'image' => 'assets/img/server-product.jpg', 'in_stock' => true,
        ]);

        $this->postJson('/cart/add', [
            'id' => $product->id, 'slug' => $product->slug,
            'name' => 'Tampered Name', 'price' => 0.01,
            'image' => 'https://attacker.invalid/image.jpg', 'qty' => 2,
        ])->assertOk()->assertJsonPath('ok', true);

        $item = collect(session('cart.items'))->first();
        $this->assertSame('Server Product', $item['name']);
        $this->assertSame(95.0, $item['price']);
        $this->assertStringContainsString('assets/img/server-product.jpg', $item['image']);
    }

    public function test_unknown_product_cannot_be_added(): void
    {
        $this->from('/cart')->post('/cart/add', [
            'slug' => 'does-not-exist', 'name' => 'Fake product', 'price' => 1,
        ])->assertRedirect('/cart')->assertSessionHasErrors('cart');
        $this->assertEmpty(session('cart.items', []));
    }

    public function test_demo_product_is_resolved_from_the_server_catalog(): void
    {
        $this->postJson('/cart/add', [
            'slug' => 'gabrini-pacific-nailpolish-89',
            'name' => 'Tampered demo name', 'price' => 0.01,
        ])->assertOk();

        $item = collect(session('cart.items'))->first();
        $this->assertNotSame('Tampered demo name', $item['name']);
        $this->assertNotSame(0.01, $item['price']);
    }

    public function test_checkout_reprices_a_tampered_session_before_creating_the_order(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'name' => 'Checkout Product', 'slug' => 'checkout-product',
            'price' => 125, 'sale_price' => 95, 'in_stock' => true,
        ]);
        $cart = ['row' => [
            'rowId' => 'row', 'id' => $product->id, 'slug' => $product->slug,
            'name' => 'Fake name', 'price' => 0.01, 'unit' => 0.01,
            'qty' => 1, 'options' => [],
        ]];

        $this->actingAs($user)->withSession(['cart.items' => $cart])
            ->post(route('checkout.confirm'), ['payment_method' => 'manual_transfer'])
            ->assertRedirect();

        $this->assertDatabaseHas('orders', ['user_id' => $user->id, 'total' => 115]);
        $this->assertDatabaseHas('order_items', ['product_name' => 'Checkout Product', 'price' => 95]);
    }

    public function test_checkout_rejects_an_out_of_stock_product_left_in_an_old_session(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'name' => 'No Longer Available', 'slug' => 'no-longer-available',
            'price' => 40, 'in_stock' => false,
        ]);
        $cart = ['stale-row' => [
            'rowId' => 'stale-row', 'id' => $product->id, 'product_id' => $product->id,
            'offer_id' => null, 'slug' => $product->slug, 'name' => $product->name,
            'price' => 40, 'unit' => 40, 'qty' => 1, 'options' => [],
        ]];

        $this->actingAs($user)->withSession(['cart.items' => $cart])
            ->post(route('checkout.confirm'), ['payment_method' => 'manual_transfer'])
            ->assertRedirect()
            ->assertSessionHasErrors('cart');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_checkout_rejects_an_unavailable_product_variant_from_an_old_session(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'name' => 'Variant Protected Product', 'slug' => 'variant-protected-product',
            'price' => 45, 'in_stock' => true,
        ]);
        $product->variants()->create([
            'size_type' => 'alpha', 'size_value' => 'M', 'color' => null, 'in_stock' => true,
        ]);
        $product->variants()->create([
            'size_type' => 'alpha', 'size_value' => 'XL', 'color' => null, 'in_stock' => false,
        ]);
        $cart = ['stale-variant' => [
            'rowId' => 'stale-variant', 'id' => $product->id, 'product_id' => $product->id,
            'offer_id' => null, 'slug' => $product->slug, 'name' => $product->name,
            'price' => 45, 'unit' => 45, 'qty' => 1, 'options' => ['size' => 'XL'],
        ]];

        $this->actingAs($user)->withSession(['cart.items' => $cart])
            ->post(route('checkout.confirm'), ['payment_method' => 'manual_transfer'])
            ->assertRedirect()
            ->assertSessionHasErrors('cart');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_cart_is_private_and_enforces_line_and_accumulated_quantity_limits(): void
    {
        $product = Product::create([
            'name' => 'Bounded Cart Product',
            'slug' => 'bounded-cart-product',
            'price' => 25,
            'in_stock' => true,
        ]);

        $added = $this->post(route('cart.add'), ['id' => $product->id, 'qty' => 600])
            ->assertRedirect(route('cart.index'))
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString('no-store', $added->headers->get('Cache-Control'));

        $this->from(route('cart.index'))
            ->post(route('cart.add'), ['id' => $product->id, 'qty' => 400])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHasErrors('qty');
        $row = collect(session('cart.items'))->first();
        $this->assertSame(600, $row['qty']);

        $page = $this->get(route('cart.index'))
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $page->headers->get('Cache-Control'));
        $count = $this->getJson(route('cart.count'))->assertOk()->assertJsonPath('count', 600);
        $this->assertStringContainsString('no-store', $count->headers->get('Cache-Control'));

        $this->assertThrows(
            fn () => Cart::update($row['rowId'], 1000),
            ValidationException::class,
        );
        $this->assertSame(600, collect(session('cart.items'))->first()['qty']);

        Cart::clear();
        foreach (range(1, Cart::MAX_LINES) as $index) {
            Cart::add([
                'id' => $index,
                'name' => 'Item '.$index,
                'price' => 1,
                'qty' => 1,
            ]);
        }
        $this->assertThrows(
            fn () => Cart::add(['id' => 101, 'name' => 'Overflow', 'price' => 1, 'qty' => 1]),
            ValidationException::class,
        );
        $this->assertCount(Cart::MAX_LINES, Cart::items());
    }

    public function test_rendered_cart_badges_and_javascript_use_the_server_session_count(): void
    {
        Cart::add([
            'id' => 'server-count-item', 'slug' => 'server-count-item',
            'name' => 'Server count item', 'price' => 10, 'qty' => 3,
        ]);

        $response = $this->get(route('home'))
            ->assertOk()
            ->assertSee('id="cartCountTop">3', false);
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control', ''));

        $script = file_get_contents(public_path('assets/app.js'));
        $this->assertIsString($script);
        $this->assertStringContainsString('window.refreshCartCount = async function', $script);
        $this->assertStringNotContainsString("localStorage.getItem('cart_count')", $script);
        $this->assertStringNotContainsString('setCartCount(getCartCount() + qty)', $script);
    }

    public function test_cart_category_navigation_uses_public_database_roots_and_working_routes(): void
    {
        Category::query()->create([
            'name' => 'Visible cart root', 'slug' => 'visible-cart-root',
            'path' => 'visible-cart-root', 'status' => 'active',
        ]);
        Category::query()->create([
            'name' => 'Hidden cart root', 'slug' => 'hidden-cart-root',
            'path' => 'hidden-cart-root', 'status' => 'hidden',
        ]);

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee(route('deals.index'), false)
            ->assertSee('/c/visible-cart-root', false)
            ->assertDontSee('/c/hidden-cart-root', false)
            ->assertDontSee('/c/super-deals', false)
            ->assertDontSee('/c/fashion', false);
    }

    public function test_cart_uses_current_shipping_settings_and_does_not_claim_stale_availability(): void
    {
        MarketplaceSetting::query()->updateOrCreate([
            'key' => 'checkout.shipping.flat_fee',
        ], [
            'value' => '7.50',
            'type' => 'decimal',
            'group' => 'checkout',
        ]);
        MarketplaceSetting::query()->updateOrCreate([
            'key' => 'checkout.shipping.free_threshold',
        ], [
            'value' => '100.00',
            'type' => 'decimal',
            'group' => 'checkout',
        ]);

        Cart::add([
            'id' => 'shipping-policy-item',
            'slug' => 'shipping-policy-item',
            'name' => 'Shipping policy item',
            'price' => 25,
            'qty' => 1,
        ]);

        $response = $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('₪7.50', false)
            ->assertSee('₪100.00', false)
            ->assertSee('button type="submit" class="btn btn-sm btn-light"', false)
            ->assertSee('يُعاد التحقق عند تأكيد الطلب')
            ->assertDontSee('متوفر بالمخازن')
            ->assertDontSee('مجاني للطلبات من 250 شيكل وأكثر، وإلا 20 شيكل.');

        $view = file_get_contents(resource_path('views/cart/index.blade.php'));
        $this->assertIsString($view);
        $this->assertStringNotContainsString('catTemplate', $view);
        $this->assertStringNotContainsString('cart-update-form', $view);
        $this->assertSame(1, substr_count($view, 'id="catBtnTpl"'));
        $this->assertStringContainsString(
            "document.getElementById('catBtnTpl')",
            file_get_contents(public_path('assets/app.js')),
        );
    }
}

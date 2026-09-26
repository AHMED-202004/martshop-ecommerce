<?php

namespace Tests\Feature;

use App\Enums\MerchantOrderStatus;
use App\Enums\MerchantVerificationStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductOfferStatus;
use App\Enums\ProductStatus;
use App\Enums\StockReservationStatus;
use App\Models\Location;
use App\Models\Merchant;
use App\Models\MerchantOrder;
use App\Models\OfferVariant;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\StockReservation;
use App\Models\User;
use App\Services\MerchantOrderService;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MerchantOrderReservationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AuthorizationSeeder::class, LocationSeeder::class]);
    }

    public function test_checkout_splits_merchants_reserves_stock_and_is_idempotent(): void
    {
        $customer = User::factory()->create();
        [$merchantA, $offerA] = $this->offer('First merchant item', 80, 5);
        [$merchantB, $offerB] = $this->offer('Second merchant item', 120, 4);
        $token = (string) Str::uuid();
        $cart = $this->cart([$offerA, 1], [$offerB, 1]);

        $this->actingAs($customer)
            ->withSession(['cart.items' => $cart, 'checkout.token' => $token])
            ->post(route('checkout.confirm'), ['payment_method' => 'manual_transfer', 'checkout_token' => $token])
            ->assertRedirect();

        $order = Order::query()->firstOrFail();
        $this->assertSame(OrderStatus::PendingConfirmation, $order->status);
        $this->assertSame('200.00', $order->subtotal);
        $this->assertSame('20.00', $order->shipping_fee);
        $this->assertSame('220.00', $order->total);
        $this->assertCount(2, $order->merchantOrders);
        $this->assertEqualsCanonicalizing(
            [$merchantA->id, $merchantB->id],
            $order->merchantOrders->pluck('merchant_id')->all(),
        );
        $this->assertSame(220.0, $order->merchantOrders->sum(fn ($row) => (float) $row->total));
        $this->assertSame(4, $offerA->fresh()->stock);
        $this->assertSame(3, $offerB->fresh()->stock);
        $this->assertDatabaseCount('stock_reservations', 2);
        $this->assertSame(
            [StockReservationStatus::Reserved, StockReservationStatus::Reserved],
            StockReservation::query()->orderBy('id')->get()->pluck('status')->all(),
        );
        $this->assertTrue($order->items->every(fn ($item) => $item->merchant_order_id !== null));

        // Replaying the same checkout token returns the original order and does not reserve twice.
        $this->actingAs($customer)
            ->withSession(['cart.items' => $cart])
            ->post(route('checkout.confirm'), ['payment_method' => 'manual_transfer', 'checkout_token' => $token])
            ->assertRedirect(route('order.confirmation', $order));

        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(4, $offerA->fresh()->stock);
        $this->assertSame(3, $offerB->fresh()->stock);
    }

    public function test_insufficient_stock_rolls_back_the_entire_checkout(): void
    {
        $customer = User::factory()->create();
        [, $offer] = $this->offer('Scarce item', 50, 1);

        $this->actingAs($customer)
            ->withSession(['cart.items' => $this->cart([$offer, 2])])
            ->from(route('cart.index'))
            ->post(route('checkout.confirm'), ['payment_method' => 'manual_transfer'])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHasErrors('cart');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('merchant_orders', 0);
        $this->assertDatabaseCount('stock_reservations', 0);
        $this->assertSame(1, $offer->fresh()->stock);
    }

    public function test_checkout_rejects_an_active_offer_variant_with_zero_stock(): void
    {
        $customer = User::factory()->create();
        [, $offer] = $this->offer('Sold-out variant item', 50, 3);
        $variant = OfferVariant::query()->create([
            'product_offer_id' => $offer->id,
            'attributes' => ['size' => 'L'],
            'stock' => 0,
            'status' => 'active',
            'source_key' => 'sold-out-l',
        ]);

        $this->actingAs($customer)
            ->withSession(['cart.items' => $this->cart([$offer, 1, ['size' => 'L']])])
            ->from(route('cart.index'))
            ->post(route('checkout.confirm'), ['payment_method' => 'manual_transfer'])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHasErrors('cart');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(3, $offer->fresh()->stock);
        $this->assertSame(0, $variant->fresh()->stock);
    }

    public function test_checkout_reserves_the_exact_selected_variant_when_attributes_repeat(): void
    {
        $customer = User::factory()->create();
        [, $offer] = $this->offer('Exact selected variant item', 50, 4);
        $first = OfferVariant::query()->create([
            'product_offer_id' => $offer->id,
            'attributes' => ['size' => 'L', 'color' => 'Red'],
            'price' => 45, 'stock' => 2, 'status' => 'active', 'source_key' => 'first-red-l',
        ]);
        $selected = OfferVariant::query()->create([
            'product_offer_id' => $offer->id,
            'attributes' => ['size' => 'L', 'color' => 'Red'],
            'price' => 40, 'stock' => 2, 'status' => 'active', 'source_key' => 'second-red-l',
        ]);
        $cart = $this->cart([$offer, 1, ['size' => 'L', 'color' => 'Red']]);
        $key = array_key_first($cart);
        $cart[$key]['offer_variant_id'] = $selected->id;

        $order = $this->checkout($customer, $cart);
        $item = $order->items()->sole();

        $this->assertSame($selected->id, $item->variant_snapshot['offer_variant_id']);
        $this->assertSame('40.00', $item->price);
        $this->assertSame(2, $first->fresh()->stock);
        $this->assertSame(1, $selected->fresh()->stock);
    }

    public function test_owner_can_confirm_but_another_merchant_cannot_act_on_the_order(): void
    {
        $customer = User::factory()->create();
        [$merchant, $offer] = $this->offer('Confirm item', 75, 3);
        [$otherMerchant] = $this->offer('Other merchant item', 25, 2);
        $order = $this->checkout($customer, $this->cart([$offer, 1]));
        $merchantOrder = $order->merchantOrders()->firstOrFail();

        $this->actingAs($otherMerchant->user)
            ->post(route('merchant.orders.confirm', $merchantOrder))
            ->assertForbidden();

        $this->actingAs($merchant->user)
            ->post(route('merchant.orders.confirm', $merchantOrder), ['current_password' => 'password'])
            ->assertRedirect();

        $this->assertSame(MerchantOrderStatus::Confirmed, $merchantOrder->fresh()->status);
        $this->assertSame(OrderStatus::Confirmed, $order->fresh()->status);
        $this->assertSame(StockReservationStatus::Consumed, $merchantOrder->reservations()->firstOrFail()->status);
        $this->assertSame(2, $offer->fresh()->stock);
    }

    public function test_rejection_restores_offer_and_variant_stock_once(): void
    {
        $customer = User::factory()->create();
        [$merchant, $offer] = $this->offer('Variant item', 60, 5);
        $variant = OfferVariant::query()->create([
            'product_offer_id' => $offer->id,
            'sku' => 'VARIANT-RED-L',
            'attributes' => ['size' => 'L', 'color' => 'Red'],
            'stock' => 3,
            'status' => 'active',
            'source_key' => 'red-l',
        ]);
        $cart = $this->cart([$offer, 2, ['size' => 'L', 'color' => 'Red']]);
        $order = $this->checkout($customer, $cart);
        $merchantOrder = $order->merchantOrders()->firstOrFail();

        $this->assertSame(3, $offer->fresh()->stock);
        $this->assertSame(1, $variant->fresh()->stock);

        $this->actingAs($merchant->user)
            ->post(route('merchant.orders.reject', $merchantOrder), [
                'current_password' => 'password',
                'reason' => 'المخزون الفعلي غير مطابق',
            ])
            ->assertRedirect();

        $this->assertSame(MerchantOrderStatus::Rejected, $merchantOrder->fresh()->status);
        $this->assertSame(OrderStatus::Rejected, $order->fresh()->status);
        $this->assertSame(StockReservationStatus::Released, $merchantOrder->reservations()->firstOrFail()->status);
        $this->assertSame(5, $offer->fresh()->stock);
        $this->assertSame(3, $variant->fresh()->stock);

        $this->actingAs($merchant->user)
            ->post(route('merchant.orders.reject', $merchantOrder), [
                'current_password' => 'password',
                'reason' => 'إعادة إرسال الرفض',
            ])
            ->assertForbidden();
        $this->assertSame(5, $offer->fresh()->stock);
        $this->assertSame(3, $variant->fresh()->stock);
    }

    public function test_expired_reservation_is_released_by_the_scheduled_command(): void
    {
        $customer = User::factory()->create();
        [, $offer] = $this->offer('Expiring item', 40, 2);
        $order = $this->checkout($customer, $this->cart([$offer, 1]));
        $merchantOrder = $order->merchantOrders()->firstOrFail();
        DB::table('stock_reservations')->where('merchant_order_id', $merchantOrder->id)
            ->update(['expires_at' => now()->subMinute()]);

        $this->artisan('reservations:release-expired')->assertSuccessful();

        $this->assertSame(MerchantOrderStatus::Expired, $merchantOrder->fresh()->status);
        $this->assertSame(OrderStatus::Expired, $order->fresh()->status);
        $this->assertSame(StockReservationStatus::Expired, $merchantOrder->reservations()->firstOrFail()->status);
        $this->assertSame(2, $offer->fresh()->stock);

        $this->artisan('reservations:release-expired')->assertSuccessful();
        $this->assertSame(2, $offer->fresh()->stock);
    }

    public function test_expired_reservation_command_rejects_unbounded_batches(): void
    {
        foreach (['invalid', '0', '1001'] as $batch) {
            $this->artisan('reservations:release-expired', ['--batch' => $batch])
                ->expectsOutput('Batch must be between 1 and 1000.')
                ->assertFailed();
        }
    }

    public function test_late_confirmation_expires_and_releases_the_reservation(): void
    {
        $customer = User::factory()->create();
        [$merchant, $offer] = $this->offer('Late confirmation item', 45, 2);
        $order = $this->checkout($customer, $this->cart([$offer, 1]));
        $merchantOrder = $order->merchantOrders()->firstOrFail();
        DB::table('stock_reservations')->where('merchant_order_id', $merchantOrder->id)
            ->update(['expires_at' => now()->subSecond()]);

        $this->actingAs($merchant->user)
            ->post(route('merchant.orders.confirm', $merchantOrder), ['current_password' => 'password'])
            ->assertRedirect()
            ->assertSessionHasErrors('order');

        $this->assertSame(MerchantOrderStatus::Expired, $merchantOrder->fresh()->status);
        $this->assertSame(StockReservationStatus::Expired, $merchantOrder->reservations()->firstOrFail()->status);
        $this->assertSame(2, $offer->fresh()->stock);
    }

    public function test_merchant_order_pages_are_private_minimized_reauthenticated_and_service_authorized(): void
    {
        $customer = User::factory()->create();
        [$merchant, $offer] = $this->offer('Private merchant order', 85, 3);
        [$otherMerchant] = $this->offer('Other protected merchant', 25, 2);
        $order = $this->checkout($customer, $this->cart([$offer, 1]));
        $merchantOrder = $order->merchantOrders()->firstOrFail();

        $indexResponse = $this->actingAs($merchant->user)
            ->get(route('merchant.orders.index'))
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString('no-store', $indexResponse->headers->get('Cache-Control'));
        $this->assertEqualsCanonicalizing(
            ['id', 'user_id', 'verification_status'],
            array_keys($indexResponse->viewData('merchant')->getAttributes()),
        );
        $listedOrder = $indexResponse->viewData('orders')->getCollection()->firstOrFail();
        $this->assertEqualsCanonicalizing(
            ['id', 'order_id', 'merchant_id', 'total', 'currency', 'status'],
            array_keys($listedOrder->getAttributes()),
        );
        $this->assertEqualsCanonicalizing(
            ['id', 'user_id'],
            array_keys($listedOrder->order->getAttributes()),
        );
        $this->assertEqualsCanonicalizing(
            ['id', 'name', 'first_name'],
            array_keys($listedOrder->order->user->getAttributes()),
        );

        $showResponse = $this->actingAs($merchant->user)
            ->get(route('merchant.orders.show', $merchantOrder))
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString('no-store', $showResponse->headers->get('Cache-Control'));
        $shownOrder = $showResponse->viewData('merchantOrder');
        $this->assertArrayNotHasKey('commission_snapshot', $shownOrder->getAttributes());
        $this->assertArrayNotHasKey('delivery_snapshot', $shownOrder->getAttributes());
        $this->assertArrayNotHasKey('rejection_reason', $shownOrder->getAttributes());
        $this->assertArrayNotHasKey('checkout_token', $shownOrder->order->getAttributes());
        $this->assertFalse($shownOrder->order->relationLoaded('user'));

        $failedConfirm = $this->actingAs($merchant->user)
            ->from(route('merchant.orders.show', $merchantOrder))
            ->post(route('merchant.orders.confirm', $merchantOrder), ['current_password' => 'wrong-password'])
            ->assertRedirect(route('merchant.orders.show', $merchantOrder))
            ->assertSessionHasErrors('current_password');
        $this->assertArrayNotHasKey('current_password', $failedConfirm->getSession()->getOldInput());

        $failedReject = $this->actingAs($merchant->user)
            ->from(route('merchant.orders.show', $merchantOrder))
            ->post(route('merchant.orders.reject', $merchantOrder), [
                'current_password' => 'wrong-password',
                'reason' => 'PRIVATE MERCHANT REJECTION',
            ])
            ->assertRedirect(route('merchant.orders.show', $merchantOrder))
            ->assertSessionHasErrors('current_password');
        $this->assertArrayNotHasKey('current_password', $failedReject->getSession()->getOldInput());
        $this->assertArrayNotHasKey('reason', $failedReject->getSession()->getOldInput());
        $this->assertSame(MerchantOrderStatus::PendingConfirmation, $merchantOrder->fresh()->status);

        $service = app(MerchantOrderService::class);
        $this->assertThrows(
            fn () => $service->confirm($merchantOrder, $otherMerchant->user),
            HttpException::class,
        );
        $this->assertThrows(
            fn () => $service->reject($merchantOrder, $otherMerchant->user, 'Service bypass'),
            HttpException::class,
        );

        $merchant->update(['verification_status' => MerchantVerificationStatus::Suspended]);
        $this->assertThrows(
            fn () => $service->confirm($merchantOrder, $merchant->user),
            HttpException::class,
        );
        $this->assertSame(MerchantOrderStatus::PendingConfirmation, $merchantOrder->fresh()->status);
        $this->assertSame(StockReservationStatus::Reserved, $merchantOrder->reservations()->firstOrFail()->status);

        $merchant->update(['verification_status' => MerchantVerificationStatus::Verified]);
        $owner = $merchant->user()->firstOrFail();
        $this->actingAs($owner)
            ->post(route('merchant.orders.confirm', $merchantOrder), ['current_password' => 'password'])
            ->assertRedirect();
        $this->assertSame(MerchantOrderStatus::Confirmed, $merchantOrder->fresh()->status);
    }

    public function test_confirmation_and_rejection_fail_closed_when_reservation_integrity_is_broken(): void
    {
        $customer = User::factory()->create();
        [$merchant, $offer] = $this->offer('Integrity protected item', 70, 4);
        $order = $this->checkout($customer, $this->cart([$offer, 2]));
        $merchantOrder = $order->merchantOrders()->firstOrFail();
        $reservation = $merchantOrder->reservations()->sole();

        DB::table('stock_reservations')->where('id', $reservation->id)->update(['quantity' => 1]);
        $this->actingAs($merchant->user)
            ->post(route('merchant.orders.confirm', $merchantOrder), ['current_password' => 'password'])
            ->assertSessionHasErrors('order');
        $this->post(route('merchant.orders.reject', $merchantOrder), [
            'current_password' => 'password', 'reason' => 'Cannot reconcile stock',
        ])->assertSessionHasErrors('order');

        $this->assertSame(MerchantOrderStatus::PendingConfirmation, $merchantOrder->fresh()->status);
        $this->assertSame(2, $offer->fresh()->stock);
        $this->assertSame(StockReservationStatus::Reserved, $reservation->fresh()->status);
        $this->assertDatabaseMissing('audit_logs', ['subject_type' => MerchantOrder::class, 'subject_id' => $merchantOrder->id]);
    }

    public function test_reservation_identity_history_and_terminal_state_are_immutable(): void
    {
        $customer = User::factory()->create();
        [$merchant, $offer] = $this->offer('Immutable reservation item', 35, 3);
        $order = $this->checkout($customer, $this->cart([$offer, 1]));
        $merchantOrder = $order->merchantOrders()->firstOrFail();
        $reservation = $merchantOrder->reservations()->sole();

        foreach (['quantity', 'delete'] as $operation) {
            try {
                $operation === 'quantity' ? $reservation->update(['quantity' => 2]) : $reservation->delete();
                $this->fail('Reservation history mutation should fail.');
            } catch (\LogicException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
            $reservation->refresh();
        }

        app(MerchantOrderService::class)->confirm($merchantOrder, $merchant->user);
        try {
            $reservation->refresh()->update(['release_reason' => 'rewritten']);
            $this->fail('Terminal reservation history should be immutable.');
        } catch (\LogicException $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }
        $this->assertArrayNotHasKey('release_reason', $reservation->fresh()->toArray());
    }

    private function checkout(User $customer, array $cart): Order
    {
        $this->actingAs($customer)
            ->withSession(['cart.items' => $cart])
            ->post(route('checkout.confirm'), ['payment_method' => 'manual_transfer'])
            ->assertRedirect();

        return Order::query()->latest('id')->firstOrFail();
    }

    private function cart(array ...$rows): array
    {
        return collect($rows)->mapWithKeys(function (array $row, int $index) {
            /** @var ProductOffer $offer */
            $offer = $row[0];
            $qty = $row[1];
            $options = $row[2] ?? [];

            return ['row-'.$index => [
                'rowId' => 'row-'.$index,
                'id' => $offer->product_id,
                'product_id' => $offer->product_id,
                'offer_id' => $offer->id,
                'slug' => $offer->product->slug,
                'name' => 'Tampered name',
                'price' => 0.01,
                'unit' => 0.01,
                'qty' => $qty,
                'options' => $options,
            ]];
        })->all();
    }

    private function offer(string $name, float $price, int $stock): array
    {
        $user = User::factory()->create();
        $location = Location::query()->firstOrFail();
        $merchant = Merchant::query()->create([
            'user_id' => $user->id,
            'location_id' => $location->id,
            'legal_name' => 'Merchant '.$user->id,
            'identity_number' => 'ORDER-'.$user->id.'-'.fake()->unique()->numerify('######'),
            'phone' => '0599'.fake()->unique()->numerify('######'),
            'date_of_birth' => now()->subYears(30),
            'address' => 'Merchant address',
            'business_type' => 'Retail',
            'verification_status' => MerchantVerificationStatus::Verified,
        ]);
        $product = Product::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'price' => $price,
            'status' => ProductStatus::Active,
            'source' => 'merchant_submission',
            'source_key' => 'order-test-'.Str::uuid(),
            'in_stock' => true,
        ]);
        $offer = ProductOffer::query()->create([
            'product_id' => $product->id,
            'merchant_id' => $merchant->id,
            'location_id' => $location->id,
            'price' => $price,
            'stock' => $stock,
            'status' => ProductOfferStatus::Active,
            'currency' => 'ILS',
            'source' => 'merchant',
            'source_key' => 'order-test-'.Str::uuid(),
            'last_confirmed_at' => now(),
        ]);

        return [$merchant, $offer];
    }
}

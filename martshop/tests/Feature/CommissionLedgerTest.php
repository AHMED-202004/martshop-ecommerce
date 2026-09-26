<?php

namespace Tests\Feature;

use App\Enums\LedgerEntryType;
use App\Enums\LedgerStatus;
use App\Enums\MerchantOrderStatus;
use App\Enums\MerchantVerificationStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductOfferStatus;
use App\Enums\ProductStatus;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\CommissionRule;
use App\Models\LedgerEntry;
use App\Models\Location;
use App\Models\Merchant;
use App\Models\MerchantOrder;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\User;
use App\Services\CommissionRuleService;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CommissionLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AuthorizationSeeder::class, LocationSeeder::class]);
    }

    public function test_checkout_uses_the_most_specific_effective_rule_and_preserves_its_snapshot(): void
    {
        $category = $this->category();
        [$merchant, $offer] = $this->offer('Commission item', 100, $category);

        CommissionRule::query()->create([
            'name' => 'General', 'percentage' => 5, 'priority' => 100, 'is_active' => true,
        ]);
        CommissionRule::query()->create([
            'name' => 'Category', 'category_id' => $category->id,
            'percentage' => 7, 'priority' => 0, 'is_active' => true,
        ]);
        CommissionRule::query()->create([
            'name' => 'Merchant', 'merchant_id' => $merchant->id,
            'percentage' => 8, 'priority' => 0, 'is_active' => true,
        ]);
        $specific = CommissionRule::query()->create([
            'name' => 'Merchant category', 'merchant_id' => $merchant->id,
            'category_id' => $category->id, 'percentage' => 10,
            'priority' => -100, 'is_active' => true,
        ]);
        CommissionRule::query()->create([
            'name' => 'Expired exact rule', 'merchant_id' => $merchant->id,
            'category_id' => $category->id, 'percentage' => 99,
            'priority' => 1000, 'is_active' => true, 'ends_at' => now()->subMinute(),
        ]);

        $first = $this->checkout(User::factory()->create(), [$offer]);
        $firstMerchantOrder = $first->merchantOrders()->firstOrFail();

        $this->assertSame('10.00', $firstMerchantOrder->commission_amount);
        $this->assertSame('10.00', $firstMerchantOrder->commission_snapshot['amount']);
        $this->assertSame($specific->id, $firstMerchantOrder->commission_snapshot['lines'][0]['rule_id']);
        $this->assertSame('10.00', $firstMerchantOrder->commission_snapshot['lines'][0]['percentage']);

        $specific->update(['percentage' => 25]);
        $second = $this->checkout(User::factory()->create(), [$offer]);

        $this->assertSame('10.00', $firstMerchantOrder->fresh()->commission_amount);
        $this->assertSame('10.00', $firstMerchantOrder->commission_snapshot['amount']);
        $this->assertSame('25.00', $second->merchantOrders()->firstOrFail()->commission_amount);
    }

    public function test_accepting_payment_creates_one_pending_sale_per_merchant_and_is_idempotent(): void
    {
        $customer = User::factory()->create();
        [$merchantA, $offerA] = $this->offer('Merchant A item', 80);
        [$merchantB, $offerB] = $this->offer('Merchant B item', 120);
        CommissionRule::query()->create([
            'name' => 'Default 10%', 'percentage' => 10, 'is_active' => true,
        ]);
        $order = $this->checkout($customer, [$offerA, $offerB]);
        foreach ($order->merchantOrders as $merchantOrder) {
            $this->actingAs($merchantOrder->merchant->user)
                ->post(route('merchant.orders.confirm', $merchantOrder), ['current_password' => 'password'])
                ->assertRedirect();
        }
        $this->assertSame(OrderStatus::Confirmed, $order->fresh()->status);

        $payment = $this->payment($order);
        $admin = $this->admin();
        $decision = ['decision' => PaymentStatus::Accepted->value, 'lock_version' => 0, 'current_password' => 'password'];
        $this->actingAs($admin)
            ->patch(route('admin.payments.update', $payment), $decision)
            ->assertRedirect(route('admin.payments.show', $payment));

        $this->assertDatabaseCount('ledger_entries', 2);
        $entries = LedgerEntry::query()->orderBy('merchant_id')->get()->keyBy('merchant_id');
        $entryA = $entries->get($merchantA->id);
        $entryB = $entries->get($merchantB->id);
        $this->assertSame(LedgerEntryType::Sale, $entryA->entry_type);
        $this->assertSame(LedgerStatus::Pending, $entryA->status);
        $this->assertSame(8000, $entryA->gross_amount);
        $this->assertSame(800, $entryA->commission_amount);
        $this->assertSame(7200, $entryA->net_amount);
        $this->assertSame(12000, $entryB->gross_amount);
        $this->assertSame(1200, $entryB->commission_amount);
        $this->assertSame(10800, $entryB->net_amount);
        $this->assertSame(OrderPaymentStatus::Paid, $order->fresh()->payment_status);
        $this->assertSame(2, AuditLog::query()->where('action', 'ledger.sale_recorded')->count());

        // Repeating the same accepted decision repairs missing entries, but never duplicates existing ones.
        $this->actingAs($admin)
            ->patch(route('admin.payments.update', $payment), $decision)
            ->assertRedirect();
        $this->assertDatabaseCount('ledger_entries', 2);
        $this->assertSame(2, AuditLog::query()->where('action', 'ledger.sale_recorded')->count());
    }

    public function test_ledger_entries_are_immutable_and_each_merchant_sees_only_their_own_ledger(): void
    {
        $customer = User::factory()->create();
        [$merchant, $offer] = $this->offer('Visible ledger item', 50);
        [$otherMerchant] = $this->offer('Other merchant item', 60);
        $order = $this->checkout($customer, [$offer]);
        $merchantOrder = $order->merchantOrders()->firstOrFail();
        $merchantOrder->update([
            'status' => MerchantOrderStatus::Confirmed,
            'confirmed_at' => now(),
        ]);
        $order->update(['status' => OrderStatus::Confirmed]);
        $payment = $this->payment($order);
        $admin = $this->admin();
        $this->actingAs($admin)->patch(route('admin.payments.update', $payment), [
            'decision' => PaymentStatus::Accepted->value,
            'lock_version' => 0,
            'current_password' => 'password',
        ])->assertRedirect();
        $entry = LedgerEntry::query()->firstOrFail();

        $merchantResponse = $this->actingAs($merchant->user)
            ->get(route('merchant.ledger.index'))
            ->assertOk()
            ->assertSee('₪50.00')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString('no-store', $merchantResponse->headers->get('Cache-Control'));
        $this->assertStringContainsString("default-src 'none'", $merchantResponse->headers->get('Content-Security-Policy'));
        $this->actingAs($otherMerchant->user)
            ->get(route('merchant.ledger.index'))
            ->assertOk()
            ->assertDontSee('₪50.00');
        $this->actingAs($customer)->get(route('admin.ledger.index'))->assertForbidden()
            ->assertDontSee($entry->reference)
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $adminResponse = $this->actingAs($admin)
            ->get(route('admin.ledger.index', ['merchant_id' => $merchant->id]))
            ->assertOk()
            ->assertSee($entry->reference)
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $adminResponse->headers->get('Cache-Control'));

        $this->assertThrows(
            fn () => $entry->update(['net_amount' => 1]),
            LogicException::class,
        );
        $this->assertThrows(
            fn () => $entry->delete(),
            LogicException::class,
        );
        $this->assertSame(5000, $entry->fresh()->net_amount);
    }

    public function test_ledger_views_are_read_only_currency_aware_and_keep_only_allowed_filters(): void
    {
        [$merchant] = $this->offer('Private ledger owner', 10);
        [$otherMerchant] = $this->offer('Other private ledger owner', 10);

        foreach (range(1, 51) as $index) {
            $currency = $index === 51 ? 'USD' : 'ILS';
            $amount = $index === 51 ? 1234 : 100;
            LedgerEntry::query()->create([
                'merchant_id' => $merchant->id,
                'entry_type' => LedgerEntryType::Adjustment,
                'direction' => 'credit',
                'amount' => $amount,
                'gross_amount' => $amount,
                'net_amount' => $amount,
                'status' => LedgerStatus::Available,
                'currency' => $currency,
                'reference' => 'PRIVATE-MERCHANT-'.$index,
                'idempotency_key' => 'private-ledger-'.Str::uuid(),
                'metadata' => ['not_for_view' => 'HIDDEN-METADATA-'.$index],
                'created_at' => now(),
            ]);
        }
        LedgerEntry::query()->create([
            'merchant_id' => $otherMerchant->id,
            'entry_type' => LedgerEntryType::Adjustment,
            'direction' => 'credit',
            'amount' => 9999,
            'gross_amount' => 9999,
            'net_amount' => 9999,
            'status' => LedgerStatus::Available,
            'currency' => 'USD',
            'reference' => 'PRIVATE-OTHER-REFERENCE',
            'idempotency_key' => 'private-ledger-'.Str::uuid(),
            'created_at' => now(),
        ]);
        $before = LedgerEntry::query()->orderBy('id')->get()->toJson();

        $merchantResponse = $this->actingAs($merchant->user)->get(route('merchant.ledger.index'))
            ->assertOk()->assertSee('USD 12.34')->assertSee('تسوية يدوية')
            ->assertDontSee('PRIVATE-OTHER-REFERENCE')->assertDontSee('HIDDEN-METADATA-51')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $merchantResponse->headers->get('Cache-Control'));

        $admin = $this->admin();
        $adminResponse = $this->actingAs($admin)->get(route('admin.ledger.index', [
            'merchant_id' => $merchant->id,
            'status' => LedgerStatus::Available->value,
            'unexpected' => 'PRIVATE-QUERY-VALUE',
        ]))->assertOk()->assertSee('USD 12.34')->assertSee('PRIVATE-MERCHANT-51')
            ->assertDontSee('PRIVATE-OTHER-REFERENCE')->assertDontSee('HIDDEN-METADATA-51')
            ->assertDontSee('PRIVATE-QUERY-VALUE')->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString('no-store', $adminResponse->headers->get('Cache-Control'));

        $this->assertSame($before, LedgerEntry::query()->orderBy('id')->get()->toJson());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_commission_management_is_permissioned_audited_and_invalid_settlement_rolls_back(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer)->get(route('admin.commissions.index'))->assertForbidden();

        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.commissions.store'), [
            'name' => 'Managed rule',
            'percentage' => '6.75',
            'priority' => 4,
            'is_active' => 1,
            'current_password' => 'password',
        ])->assertRedirect();
        $rule = CommissionRule::query()->firstOrFail();
        $this->assertSame('6.75', $rule->percentage);
        $this->assertTrue(AuditLog::query()->where('action', 'commission_rule.created')->exists());

        $this->actingAs($admin)->put(route('admin.commissions.update', $rule), [
            'name' => 'Managed rule updated',
            'percentage' => '7.50',
            'priority' => 5,
            'is_active' => 1,
            'current_password' => 'password',
        ])->assertRedirect();
        $this->assertSame('7.50', $rule->fresh()->percentage);
        $this->assertTrue(AuditLog::query()->where('action', 'commission_rule.updated')->exists());

        [$merchant, $offer] = $this->offer('Invalid settlement item', 40);
        $order = $this->checkout($customer, [$offer]);
        $merchantOrder = $order->merchantOrders()->firstOrFail();
        $merchantOrder->update([
            'status' => MerchantOrderStatus::Confirmed,
            'commission_amount' => 50,
            'confirmed_at' => now(),
        ]);
        $order->update(['status' => OrderStatus::Confirmed]);
        $payment = $this->payment($order);

        $this->actingAs($admin)
            ->from(route('admin.payments.show', $payment))
            ->patch(route('admin.payments.update', $payment), [
                'decision' => PaymentStatus::Accepted->value,
                'lock_version' => 0,
                'current_password' => 'password',
            ])
            ->assertRedirect(route('admin.payments.show', $payment))
            ->assertSessionHasErrors('payment');

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertSame(OrderPaymentStatus::Unpaid, $order->fresh()->payment_status);
        $this->assertDatabaseCount('ledger_entries', 0);
        $this->assertSame($merchant->id, $merchantOrder->merchant_id);
    }

    public function test_commission_changes_are_private_reauthenticated_and_service_permissioned(): void
    {
        $admin = $this->admin();
        $response = $this->actingAs($admin)->get(route('admin.commissions.index'))
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString("default-src 'none'", $response->headers->get('Content-Security-Policy'));

        $failedCreate = $this->actingAs($admin)
            ->from(route('admin.commissions.index'))
            ->post(route('admin.commissions.store'), [
                'name' => 'Rejected rule',
                'percentage' => '12.50',
                'priority' => 1,
                'is_active' => 1,
                'current_password' => 'wrong-password',
            ])
            ->assertRedirect(route('admin.commissions.index'))
            ->assertSessionHasErrors('current_password');
        $this->assertArrayNotHasKey('current_password', $failedCreate->getSession()->getOldInput());
        $this->assertDatabaseMissing('commission_rules', ['name' => 'Rejected rule']);

        $rule = CommissionRule::query()->create([
            'name' => 'Existing protected rule',
            'percentage' => '4.00',
            'priority' => 0,
            'is_active' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        $this->actingAs($admin)->put(route('admin.commissions.update', $rule), [
            'name' => 'Rejected update',
            'percentage' => '99.00',
            'priority' => 9,
            'is_active' => 1,
            'current_password' => 'wrong-password',
        ])->assertSessionHasErrors('current_password');
        $this->assertSame('Existing protected rule', $rule->fresh()->name);
        $this->assertDatabaseCount('audit_logs', 0);

        $ordinary = User::factory()->create();
        $this->assertThrows(
            fn () => app(CommissionRuleService::class)->create([
                'name' => 'Service bypass',
                'percentage' => '5.00',
            ], $ordinary),
            HttpException::class,
        );
        $this->assertDatabaseMissing('commission_rules', ['name' => 'Service bypass']);
    }

    private function checkout(User $customer, array $offers): Order
    {
        $cart = collect($offers)->mapWithKeys(fn (ProductOffer $offer, int $index) => [
            'row-'.$index => [
                'rowId' => 'row-'.$index,
                'id' => $offer->product_id,
                'product_id' => $offer->product_id,
                'offer_id' => $offer->id,
                'slug' => $offer->product->slug,
                'name' => 'Untrusted name',
                'price' => 0.01,
                'unit' => 0.01,
                'qty' => 1,
                'options' => [],
            ],
        ])->all();

        $this->actingAs($customer)
            ->withSession(['cart.items' => $cart])
            ->post(route('checkout.confirm'), ['payment_method' => 'manual_transfer'])
            ->assertRedirect();

        return Order::query()->latest('id')->firstOrFail();
    }

    private function payment(Order $order): Payment
    {
        return Payment::query()->create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'order_no' => 'ORDER-'.$order->id,
            'amount' => (int) round((float) $order->total * 100),
            'currency' => $order->currency,
            'provider' => 'manual',
            'provider_ref' => 'LEDGER-'.$order->id.'-'.Str::random(8),
            'status' => PaymentStatus::Pending,
            'lock_version' => 0,
        ]);
    }

    private function offer(string $name, float $price, ?Category $category = null): array
    {
        $user = User::factory()->create();
        $location = Location::query()->firstOrFail();
        $merchant = Merchant::query()->create([
            'user_id' => $user->id,
            'location_id' => $location->id,
            'legal_name' => 'Merchant '.$user->id,
            'identity_number' => 'LEDGER-'.$user->id.'-'.fake()->unique()->numerify('######'),
            'phone' => '0599'.fake()->unique()->numerify('######'),
            'date_of_birth' => now()->subYears(30),
            'address' => 'Merchant address',
            'business_type' => 'Retail',
            'verification_status' => MerchantVerificationStatus::Verified,
        ]);
        $product = Product::query()->create([
            'category_id' => $category?->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'price' => $price,
            'status' => ProductStatus::Active,
            'source' => 'merchant_submission',
            'source_key' => 'ledger-test-'.Str::uuid(),
            'in_stock' => true,
        ]);
        $offer = ProductOffer::query()->create([
            'product_id' => $product->id,
            'merchant_id' => $merchant->id,
            'location_id' => $location->id,
            'price' => $price,
            'stock' => 20,
            'status' => ProductOfferStatus::Active,
            'currency' => 'ILS',
            'source' => 'merchant',
            'source_key' => 'ledger-test-'.Str::uuid(),
            'last_confirmed_at' => now(),
        ]);

        return [$merchant, $offer];
    }

    private function category(): Category
    {
        return Category::query()->create([
            'name' => 'Commission category',
            'slug' => 'commission-category',
            'path' => 'commission-category',
            'depth' => 0,
            'status' => 'active',
        ]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }
}

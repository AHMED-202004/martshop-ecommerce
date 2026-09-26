<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\DeliveryStatus;
use App\Enums\DeliveryWorkerAvailability;
use App\Enums\LedgerEntryType;
use App\Enums\LedgerStatus;
use App\Enums\MerchantOrderStatus;
use App\Enums\MerchantVerificationStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductOfferStatus;
use App\Enums\ProductStatus;
use App\Models\AuditLog;
use App\Models\Delivery;
use App\Models\DeliveryEvent;
use App\Models\DeliveryProof;
use App\Models\DeliveryRating;
use App\Models\DeliveryWorkerProfile;
use App\Models\LedgerEntry;
use App\Models\Location;
use App\Models\MarketplaceSetting;
use App\Models\Merchant;
use App\Models\MerchantOrder;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DeliveryAssignmentService;
use App\Services\DeliveryConfirmationService;
use App\Services\DeliveryDisputeService;
use App\Services\DeliveryWorkerService;
use App\Services\MerchantLedgerService;
use App\Services\StaffSecurityService;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DeliveryAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AuthorizationSeeder::class, LocationSeeder::class]);
    }

    public function test_checkout_captures_an_immutable_delivery_address_and_rejects_an_incomplete_one(): void
    {
        $customer = User::factory()->create([
            'first_name' => 'Ahmad',
            'last_name' => 'Saleh',
            'governorate' => 'Gaza',
            'city' => 'Khan Younis',
            'address' => 'Old delivery address',
            'mobile' => '0599000111',
        ]);
        [, $offer] = $this->offer('Address snapshot item', 50);
        $order = $this->checkout($customer, $offer);

        $this->assertSame('Ahmad Saleh', $order->delivery_address_snapshot['recipient_name']);
        $this->assertSame('Old delivery address', $order->delivery_address_snapshot['address']);
        $this->assertSame('0599000111', $order->delivery_address_snapshot['mobile']);
        $customer->update(['address' => 'New profile address', 'mobile' => '0599000222']);
        $this->assertSame('Old delivery address', $order->fresh()->delivery_address_snapshot['address']);
        $this->assertSame('0599000111', $order->delivery_address_snapshot['mobile']);

        $incompleteCustomer = User::factory()->create(['address' => null, 'city' => null]);
        [, $otherOffer] = $this->offer('Incomplete address item', 40);
        $this->actingAs($incompleteCustomer)
            ->withSession(['cart.items' => $this->cart($otherOffer)])
            ->from(route('cart.index'))
            ->post(route('checkout.confirm'), ['payment_method' => 'manual_transfer'])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHasErrors('address');
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(10, $otherOffer->fresh()->stock);
    }

    public function test_admin_promotes_a_separate_registered_account_to_delivery_worker(): void
    {
        $admin = $this->admin();
        $candidate = User::factory()->create();

        $this->actingAs($candidate)
            ->post(route('admin.deliveries.workers.promote'), ['email' => $candidate->email])
            ->assertForbidden();
        $this->actingAs($admin)
            ->post(route('admin.deliveries.workers.promote'), ['email' => mb_strtoupper($candidate->email)])
            ->assertRedirect();

        $this->assertTrue($candidate->fresh()->hasRole('delivery-worker'));
        $this->assertTrue($candidate->hasPermission('deliveries.view-own'));
        $this->assertTrue($candidate->hasPermission('deliveries.accept'));
        $this->assertTrue($candidate->hasPermission('deliveries.update-status'));
        $this->assertTrue($candidate->hasPermission('deliveries.confirm'));
        $this->assertFalse($candidate->hasPermission('deliveries.manage'));
        $this->assertTrue(AuditLog::query()->where('action', 'delivery_worker.role_assigned')->exists());

        $merchant = $this->merchant();
        $this->actingAs($admin)
            ->from(route('admin.deliveries.index'))
            ->post(route('admin.deliveries.workers.promote'), ['email' => $merchant->user->email])
            ->assertRedirect(route('admin.deliveries.index'))
            ->assertSessionHasErrors('email');
        $this->assertFalse($merchant->user->hasRole('delivery-worker'));
    }

    public function test_delivery_services_recheck_permissions_for_direct_internal_calls(): void
    {
        $ordinary = User::factory()->create();
        $candidate = User::factory()->create();
        $worker = $this->worker();
        $merchantOrder = $this->merchantOrder(OrderPaymentStatus::Paid);

        $this->assertThrows(
            fn () => app(DeliveryWorkerService::class)
                ->promoteByEmail($candidate->email, $ordinary),
            HttpException::class,
        );
        $this->assertFalse($candidate->fresh()->hasRole('delivery-worker'));

        $this->assertThrows(
            fn () => app(DeliveryAssignmentService::class)
                ->assign($merchantOrder, $worker, $ordinary, 'PRIVATE ASSIGNMENT NOTE', 0),
            HttpException::class,
        );
        $this->assertDatabaseCount('deliveries', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_assignment_requires_confirmed_paid_order_and_an_authorized_worker(): void
    {
        $admin = $this->admin();
        $worker = $this->worker();
        $ordinaryUser = User::factory()->create();
        $merchantOrder = $this->merchantOrder(OrderPaymentStatus::Unpaid);
        $payload = ['delivery_worker_id' => $worker->id, 'lock_version' => 0];

        $this->actingAs($ordinaryUser)
            ->post(route('admin.deliveries.assign', $merchantOrder), $payload)
            ->assertForbidden();
        $this->actingAs($admin)
            ->from(route('admin.deliveries.index'))
            ->post(route('admin.deliveries.assign', $merchantOrder), $payload)
            ->assertRedirect(route('admin.deliveries.index'))
            ->assertSessionHasErrors('delivery');
        $this->assertDatabaseCount('deliveries', 0);

        $merchantOrder->order->update(['payment_status' => OrderPaymentStatus::Paid]);
        $adminPage = $this->actingAs($admin)->get(route('admin.deliveries.index'))
            ->assertOk()->assertSee('Customer delivery street')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString('no-store', $adminPage->headers->get('Cache-Control'));
        $this->assertStringContainsString("default-src 'none'", $adminPage->headers->get('Content-Security-Policy'));
        $this->actingAs($admin)
            ->post(route('admin.deliveries.assign', $merchantOrder), [
                'delivery_worker_id' => $ordinaryUser->id,
                'lock_version' => 0,
            ])->assertSessionHasErrors('delivery_worker_id');

        $worker->forceFill(['account_status' => AccountStatus::OnLeave])->save();
        $this->actingAs($admin)
            ->post(route('admin.deliveries.assign', $merchantOrder), $payload)
            ->assertSessionHasErrors('delivery_worker_id');
        $this->actingAs($admin)->get(route('admin.deliveries.index'))
            ->assertOk()->assertDontSee($worker->email);
        $worker->forceFill(['account_status' => AccountStatus::Active])->save();

        $this->actingAs($admin)
            ->post(route('admin.deliveries.assign', $merchantOrder), $payload)
            ->assertRedirect();
        $this->actingAs($admin)
            ->post(route('admin.deliveries.assign', $merchantOrder), $payload)
            ->assertRedirect();

        $delivery = Delivery::query()->firstOrFail();
        $this->assertSame(DeliveryStatus::Assigned, $delivery->status);
        $this->assertSame($worker->id, $delivery->delivery_worker_id);
        $this->assertSame('Customer delivery street', $delivery->destination_snapshot['address']);
        $this->assertSame($merchantOrder->merchant->address, $delivery->origin_snapshot['address']);
        $this->assertDatabaseCount('deliveries', 1);
        $this->assertDatabaseCount('delivery_events', 1);
        $this->assertSame(1, AuditLog::query()->where('action', 'delivery.assigned')->count());
        $this->assertThrows(
            fn () => app(StaffSecurityService::class)
                ->terminate($admin, $worker->id, $worker->email, 'Active delivery must be reassigned'),
            ValidationException::class,
        );
        $this->assertSame(AccountStatus::Active, $worker->fresh()->account_status);
    }

    public function test_reassignment_and_worker_acceptance_are_scoped_locked_and_idempotent(): void
    {
        $admin = $this->admin();
        $workerA = $this->worker();
        $workerB = $this->worker();
        $merchantOrder = $this->merchantOrder(OrderPaymentStatus::Paid);
        $this->actingAs($admin)->post(route('admin.deliveries.assign', $merchantOrder), [
            'delivery_worker_id' => $workerA->id,
            'lock_version' => 0,
        ]);
        $delivery = Delivery::query()->firstOrFail();

        $this->actingAs($admin)->post(route('admin.deliveries.assign', $merchantOrder), [
            'delivery_worker_id' => $workerB->id,
            'assignment_notes' => 'Worker B is closer',
            'lock_version' => 0,
        ])->assertRedirect();
        $delivery->refresh();
        $this->assertSame($workerB->id, $delivery->delivery_worker_id);
        $this->assertSame(1, $delivery->lock_version);
        $this->assertSame(2, $delivery->events()->count());

        $this->actingAs($admin)
            ->post(route('admin.deliveries.assign', $merchantOrder), [
                'delivery_worker_id' => $workerA->id,
                'lock_version' => 0,
            ])->assertSessionHasErrors('lock_version');
        $this->assertSame($workerB->id, $delivery->fresh()->delivery_worker_id);

        $this->actingAs($workerA)
            ->get(route('delivery.tasks.index'))
            ->assertOk()
            ->assertDontSee($delivery->reference);
        $this->actingAs($workerA)
            ->post(route('delivery.tasks.accept', $delivery), ['lock_version' => 1])
            ->assertForbidden();
        $workerPage = $this->actingAs($workerB)
            ->get(route('delivery.tasks.index'))
            ->assertOk()
            ->assertSee($delivery->reference)
            ->assertSee('Customer delivery street')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString('no-store', $workerPage->headers->get('Cache-Control'));
        $this->assertStringContainsString("default-src 'none'", $workerPage->headers->get('Content-Security-Policy'));

        $accept = ['lock_version' => 1];
        $this->actingAs($workerB)
            ->post(route('delivery.tasks.accept', $delivery), $accept)
            ->assertRedirect();
        $this->actingAs($workerB)
            ->post(route('delivery.tasks.accept', $delivery), $accept)
            ->assertRedirect();

        $delivery->refresh();
        $this->assertSame(DeliveryStatus::Accepted, $delivery->status);
        $this->assertSame(2, $delivery->lock_version);
        $this->assertSame(3, $delivery->events()->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'delivery.accepted')->count());

        $this->actingAs($admin)
            ->post(route('admin.deliveries.assign', $merchantOrder), [
                'delivery_worker_id' => $workerA->id,
                'lock_version' => 2,
            ])->assertSessionHasErrors('delivery');
        $this->assertSame($workerB->id, $delivery->fresh()->delivery_worker_id);
    }

    public function test_platform_order_and_incomplete_historical_address_are_not_assignable(): void
    {
        $admin = $this->admin();
        $worker = $this->worker();
        $merchantOrder = $this->merchantOrder(OrderPaymentStatus::Paid);
        $merchantOrder->update(['merchant_id' => null, 'group_key' => 'platform']);

        $this->actingAs($admin)
            ->post(route('admin.deliveries.assign', $merchantOrder), [
                'delivery_worker_id' => $worker->id,
                'lock_version' => 0,
            ])->assertSessionHasErrors('delivery');
        $this->assertDatabaseCount('deliveries', 0);

        $merchant = $this->merchant();
        $merchantOrder->update(['merchant_id' => $merchant->id, 'group_key' => 'merchant:'.$merchant->id]);
        $merchantOrder->order->update(['delivery_address_snapshot' => ['recipient_name' => 'Missing Address']]);
        $this->actingAs($admin)
            ->post(route('admin.deliveries.assign', $merchantOrder), [
                'delivery_worker_id' => $worker->id,
                'lock_version' => 0,
            ])->assertSessionHasErrors('delivery');
        $this->assertDatabaseCount('deliveries', 0);
    }

    public function test_delivery_events_cannot_be_changed_or_deleted(): void
    {
        $admin = $this->admin();
        $worker = $this->worker();
        $merchantOrder = $this->merchantOrder(OrderPaymentStatus::Paid);
        $this->actingAs($admin)->post(route('admin.deliveries.assign', $merchantOrder), [
            'delivery_worker_id' => $worker->id,
            'lock_version' => 0,
        ]);
        $event = DeliveryEvent::query()->firstOrFail();

        $this->assertThrows(fn () => $event->update(['event_type' => 'tampered']), LogicException::class);
        $this->assertThrows(fn () => $event->delete(), LogicException::class);
        $this->assertSame('assigned', $event->fresh()->event_type);
    }

    public function test_delivery_confirmation_requires_ordered_states_pin_and_private_proof_and_settles_once(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $worker = $this->worker();
        $merchantOrder = $this->merchantOrder(OrderPaymentStatus::Paid);
        LedgerEntry::query()->create([
            'merchant_id' => $merchantOrder->merchant_id,
            'order_id' => $merchantOrder->order_id,
            'merchant_order_id' => $merchantOrder->id,
            'entry_type' => LedgerEntryType::Sale,
            'direction' => 'credit',
            'amount' => 9500,
            'gross_amount' => 10000,
            'commission_amount' => 500,
            'fee_amount' => 0,
            'net_amount' => 9500,
            'status' => LedgerStatus::Pending,
            'currency' => 'ILS',
            'reference' => 'delivery-sale-'.$merchantOrder->id,
            'idempotency_key' => 'delivery-sale-'.$merchantOrder->id,
            'created_by' => $admin->id,
            'created_at' => now(),
        ]);
        $this->actingAs($admin)->post(route('admin.deliveries.assign', $merchantOrder), [
            'delivery_worker_id' => $worker->id, 'lock_version' => 0,
        ]);
        $delivery = Delivery::query()->firstOrFail();
        $pin = $delivery->confirmation_pin;
        $this->assertMatchesRegularExpression('/^\d{4}$/', $pin);
        $this->actingAs($merchantOrder->order->user)->get(route('orders.history'))
            ->assertOk()->assertSee($pin);

        $this->actingAs($worker)->post(route('delivery.tasks.picked-up', $delivery), [
            'lock_version' => 0,
        ])->assertSessionHasErrors('delivery');
        $this->actingAs($worker)->post(route('delivery.tasks.accept', $delivery), [
            'lock_version' => 0,
        ])->assertRedirect();
        $this->actingAs($worker)->post(route('delivery.tasks.picked-up', $delivery), [
            'lock_version' => 1, 'note' => 'Parcel sealed',
        ])->assertRedirect();
        $this->actingAs($worker)->post(route('delivery.tasks.in-transit', $delivery), [
            'lock_version' => 2,
        ])->assertRedirect();

        $this->actingAs($worker)->post(route('delivery.tasks.delivered', $delivery), [
            'lock_version' => 3,
            'pin' => $pin === '9999' ? '1111' : '9999',
            'proof' => UploadedFile::fake()->create('wrong-pin.jpg', 20, 'image/jpeg'),
        ])->assertSessionHasErrors('pin');
        $this->assertDatabaseCount('delivery_proofs', 0);
        Storage::disk('local')->assertDirectoryEmpty('delivery-proofs');

        $payload = [
            'lock_version' => 3,
            'pin' => $pin,
            'proof' => UploadedFile::fake()->create('delivery.jpg', 20, 'image/jpeg'),
            'note' => 'Delivered to customer',
        ];
        $this->actingAs($worker)->post(route('delivery.tasks.delivered', $delivery), $payload)->assertRedirect();
        $delivery->refresh();
        $this->assertSame(DeliveryStatus::Delivered, $delivery->status);
        $this->assertNotNull($delivery->delivered_at);
        $this->assertDatabaseCount('delivery_proofs', 1);
        $this->assertDatabaseCount('delivery_events', 5);
        $this->assertSame(9500, (int) LedgerEntry::query()->where('merchant_id', $merchantOrder->merchant_id)->where('status', 'pending')->sum('net_amount'));
        $this->assertSame(0, (int) LedgerEntry::query()->where('merchant_id', $merchantOrder->merchant_id)->where('status', 'available')->sum('net_amount'));
        $this->assertSame(7, $delivery->settlement_hold_days);
        $this->assertTrue($delivery->settlement_due_at->equalTo($delivery->delivered_at->copy()->addDays(7)));

        $payload['proof'] = UploadedFile::fake()->create('retry.jpg', 20, 'image/jpeg');
        $this->actingAs($worker)->post(route('delivery.tasks.delivered', $delivery), $payload)->assertRedirect();
        $this->assertDatabaseCount('delivery_proofs', 1);
        $this->assertDatabaseCount('ledger_entries', 1);
        $this->actingAs(User::factory()->create())
            ->get(route('delivery-proofs.show', $delivery->proof))->assertForbidden();
        $this->actingAs($admin)->get(route('delivery-proofs.show', $delivery->proof))->assertOk();
        $this->actingAs($merchantOrder->order->user)->get(route('orders.history'))
            ->assertOk()->assertDontSee('رمز الاستلام: '.$pin);

        $this->setSettlementSetting('settlement.auto_release_enabled', '1');
        $this->travelTo($delivery->settlement_due_at->copy()->subSecond());
        $this->assertThrows(fn () => app(MerchantLedgerService::class)->releaseDeliveredSale($delivery), ValidationException::class);
        $this->assertDatabaseCount('ledger_entries', 1);
        $this->travelTo($delivery->settlement_due_at);
        $ledger = app(MerchantLedgerService::class);
        $ledger->releaseDeliveredSale($delivery);
        $ledger->releaseDeliveredSale($delivery);
        $this->assertDatabaseCount('ledger_entries', 3);
        $this->assertNotNull($delivery->fresh()->settled_at);
        $this->assertSame(9500, (int) LedgerEntry::query()->where('status', 'available')->sum('net_amount'));
    }

    public function test_invalid_hold_policy_rolls_back_confirmation_and_removes_the_uploaded_proof(): void
    {
        Storage::fake('local');
        [$delivery, $worker] = $this->inTransitDelivery();
        $this->setSettlementSetting('settlement.dispute_days', '0');
        $this->assertThrows(fn () => app(DeliveryConfirmationService::class)->confirmDelivered(
            $delivery, $worker, $delivery->lock_version, $delivery->confirmation_pin,
            UploadedFile::fake()->create('proof.jpg', 20, 'image/jpeg'), 'Received',
        ), ValidationException::class);

        $this->assertSame(DeliveryStatus::InTransit, $delivery->fresh()->status);
        $this->assertNull($delivery->fresh()->delivered_at);
        $this->assertSame(3, $delivery->fresh()->lock_version);
        $this->assertDatabaseCount('delivery_proofs', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
        $this->assertSame(4, $delivery->events()->count());
        $this->assertSame([], Storage::disk('local')->allFiles('delivery-proofs'));
        $this->assertDatabaseMissing('audit_logs', ['action' => 'delivery.delivered']);
    }

    public function test_delivery_pin_and_timestamp_are_sufficient_when_optional_photo_is_omitted(): void
    {
        Storage::fake('local');
        [$delivery, $worker] = $this->inTransitDelivery();

        $this->actingAs($worker)->post(route('delivery.tasks.delivered', $delivery), [
            'lock_version' => 3,
            'pin' => $delivery->confirmation_pin,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $delivery->refresh();
        $this->assertSame(DeliveryStatus::Delivered, $delivery->status);
        $this->assertNotNull($delivery->delivered_at);
        $this->assertDatabaseCount('delivery_proofs', 0);
        $this->assertSame(false, $delivery->events()->latest('id')->firstOrFail()->metadata['proof_attached']);
    }

    public function test_confirmation_checks_worker_version_file_and_pin_privacy(): void
    {
        Storage::fake('local');
        [$delivery, $worker] = $this->inTransitDelivery();
        $pin = $delivery->confirmation_pin;
        $this->assertNotSame($pin, $delivery->getRawOriginal('confirmation_pin'));
        $this->assertArrayNotHasKey('confirmation_pin', $delivery->toArray());
        $this->assertArrayNotHasKey('confirmation_pin_hash', $delivery->toArray());
        $this->actingAs($worker)->get(route('delivery.tasks.index'))->assertOk()->assertDontSee('رمز الاستلام:');

        $payload = ['lock_version' => 3, 'pin' => $pin,
            'proof' => UploadedFile::fake()->create('proof.jpg', 20, 'image/jpeg')];
        $this->actingAs($this->worker())->post(route('delivery.tasks.delivered', $delivery), $payload)
            ->assertForbidden();
        $payload['lock_version'] = 2;
        $this->actingAs($worker)->post(route('delivery.tasks.delivered', $delivery), $payload)
            ->assertSessionHasErrors('lock_version');
        $payload['lock_version'] = 3;
        $payload['proof'] = UploadedFile::fake()->create('script.html', 1, 'text/html');
        $this->actingAs($worker)->post(route('delivery.tasks.delivered', $delivery), $payload)
            ->assertSessionHasErrors('proof');

        $this->assertThrows(
            fn () => app(DeliveryConfirmationService::class)->confirmDelivered(
                $delivery,
                $worker,
                3,
                $pin,
                UploadedFile::fake()->create('direct-script.html', 1, 'text/html'),
                'Direct service attempt',
            ),
            ValidationException::class,
        );
        $this->assertDatabaseCount('delivery_proofs', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('delivery-proofs'));
        $this->assertSame(DeliveryStatus::InTransit, $delivery->fresh()->status);
    }

    public function test_delivery_proof_download_is_private_and_fails_closed_when_tampered(): void
    {
        Storage::fake('local');
        [$delivery] = $this->inTransitDelivery();
        $admin = $this->admin();
        $contents = 'private delivery proof bytes';
        $path = 'delivery-proofs/'.$delivery->id.'/'.Str::ulid().'.jpg';
        Storage::disk('local')->put($path, $contents);
        $proof = DeliveryProof::query()->create([
            'delivery_id' => $delivery->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => "private\r\nname.jpg",
            'mime_type' => 'image/jpeg',
            'size' => strlen($contents),
            'sha256' => hash('sha256', $contents),
            'uploaded_by' => $delivery->delivery_worker_id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('delivery-proofs.show', $proof))
            ->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Type', 'application/octet-stream');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('private', $response->headers->get('Content-Disposition'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'delivery.proof_viewed']);

        DB::table('delivery_proofs')->where('id', $proof->id)->update(['size' => strlen($contents) + 1]);
        $this->actingAs($admin)->get(route('delivery-proofs.show', $proof->fresh()))->assertNotFound();
        DB::table('delivery_proofs')->where('id', $proof->id)->update([
            'size' => strlen($contents), 'sha256' => str_repeat('0', 64),
        ]);
        $this->actingAs($admin)->get(route('delivery-proofs.show', $proof->fresh()))->assertNotFound();
        DB::table('delivery_proofs')->where('id', $proof->id)->update([
            'sha256' => hash('sha256', $contents),
            'path' => 'delivery-proofs/'.$delivery->id.'/../outside.jpg',
        ]);
        $this->actingAs($admin)->get(route('delivery-proofs.show', $proof->fresh()))->assertNotFound();
        DB::table('delivery_proofs')->where('id', $proof->id)->update(['path' => $path, 'disk' => 'public']);
        $this->actingAs($admin)->get(route('delivery-proofs.show', $proof->fresh()))->assertNotFound();
        $this->assertSame(1, AuditLog::query()->where('action', 'delivery.proof_viewed')->count());
    }

    public function test_open_dispute_holds_only_its_sale_and_prevents_release_until_staff_closes_it(): void
    {
        [$delivery] = $this->deliveredSale();
        $owner = $delivery->order->user;
        $other = User::factory()->create();
        $this->actingAs($other)->post(route('delivery-disputes.store', $delivery), ['reason' => 'Damaged package'])->assertForbidden();
        $this->actingAs($owner)->post(route('delivery-disputes.store', $delivery), ['reason' => 'Damaged package'])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('delivery-disputes.store', $delivery), ['reason' => 'Duplicate submit'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('delivery_disputes', 1);
        $this->assertDatabaseCount('ledger_entries', 3);
        $this->assertSame(0, (int) LedgerEntry::query()->where('status', 'pending')->sum('net_amount'));
        $this->assertSame(9500, (int) LedgerEntry::query()->where('status', 'held')->sum('net_amount'));
        $ledger = app(MerchantLedgerService::class);
        $this->assertThrows(
            fn () => $ledger->holdDisputedSale($delivery, $other),
            HttpException::class,
        );
        $this->assertThrows(
            fn () => $ledger->restoreDisputedSale($delivery, $other),
            HttpException::class,
        );
        $this->setSettlementSetting('settlement.auto_release_enabled', '1');
        $this->travelTo($delivery->settlement_due_at->copy()->addMinute());
        $this->artisan('settlements:release-due')->assertSuccessful();
        $this->assertNull($delivery->fresh()->settled_at);
        $this->assertThrows(fn () => app(MerchantLedgerService::class)->releaseDeliveredSale($delivery), ValidationException::class);
        $this->actingAs($owner)->post(route('admin.settlements.close-dispute', $delivery), ['reason' => 'Cannot self approve'])->assertForbidden();
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.settlements.index'))->assertOk()->assertSee('Damaged package');
        foreach ([1, 2] as $attempt) {
            $this->actingAs($admin)->post(route('admin.settlements.close-dispute', $delivery), [
                'reason' => 'Reviewed and resolved', 'current_password' => 'password',
            ])->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('ledger_entries', 5);
        $this->assertSame(0, (int) LedgerEntry::query()->where('status', 'held')->sum('net_amount'));
        $this->assertSame(9500, (int) LedgerEntry::query()->where('status', 'pending')->sum('net_amount'));
        $this->artisan('settlements:release-due')->assertSuccessful();
        $this->artisan('settlements:release-due')->assertSuccessful();
        $this->assertDatabaseCount('ledger_entries', 7);
        $this->assertNotNull($delivery->fresh()->settled_at);
    }

    public function test_staff_dispute_hold_requires_password_without_flashing_the_reason(): void
    {
        [$delivery] = $this->deliveredSale();
        $admin = $this->admin();
        $ledgerBefore = LedgerEntry::query()->orderBy('id')->get()->toJson();
        $auditBefore = AuditLog::query()->count();

        $this->actingAs($admin)->from(route('admin.settlements.index'))
            ->post(route('delivery-disputes.store', $delivery), [
                'reason' => 'PRIVATE ADMINISTRATIVE HOLD',
                'current_password' => 'wrong-password',
            ])->assertRedirect(route('admin.settlements.index'))
            ->assertSessionHasErrors('current_password')
            ->assertSessionMissing('_old_input.current_password')
            ->assertSessionMissing('_old_input.reason')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertDatabaseCount('delivery_disputes', 0);
        $this->assertSame($ledgerBefore, LedgerEntry::query()->orderBy('id')->get()->toJson());
        $this->assertSame($auditBefore, AuditLog::query()->count());

        $this->post(route('delivery-disputes.store', $delivery), [
            'reason' => 'Administrative hold after review',
            'current_password' => 'password',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('delivery_disputes', 1);
        $this->assertSame(0, (int) LedgerEntry::query()->where('status', 'pending')->sum('net_amount'));
        $this->assertSame(9500, (int) LedgerEntry::query()->where('status', 'held')->sum('net_amount'));
    }

    public function test_closing_dispute_and_changing_settings_do_not_shorten_the_snapshotted_deadline(): void
    {
        [$delivery] = $this->deliveredSale();
        $deadline = $delivery->settlement_due_at->toIso8601String();
        $this->setSettlementSetting('settlement.dispute_days', '1');
        $this->setSettlementSetting('settlement.auto_release_enabled', '1');
        $disputes = app(DeliveryDisputeService::class);
        $disputes->open($delivery, $delivery->order->user, 'Problem with package');
        $disputes->close($delivery, $this->admin(), 'Reviewed and resolved');
        $this->assertSame($deadline, $delivery->fresh()->settlement_due_at->toIso8601String());
        $this->travel(2)->days();
        $this->artisan('settlements:release-due')->assertSuccessful();
        $this->assertNull($delivery->fresh()->settled_at);
        $this->assertSame(0, (int) LedgerEntry::query()->where('status', 'available')->sum('net_amount'));
    }

    public function test_settlement_is_disabled_by_default_and_late_customer_disputes_are_rejected(): void
    {
        [$delivery] = $this->deliveredSale();
        $this->travelTo($delivery->settlement_due_at);
        $this->artisan('settlements:release-due')->expectsOutput('Automatic settlement release is disabled.')->assertSuccessful();
        $this->assertNull($delivery->fresh()->settled_at);
        $this->actingAs($delivery->order->user)->post(route('delivery-disputes.store', $delivery), ['reason' => 'Late complaint'])->assertSessionHasErrors('dispute');
        $this->assertDatabaseCount('delivery_disputes', 0);
        // Finance can still stop an overdue but unreleased sale.
        app(DeliveryDisputeService::class)->open($delivery, $this->admin(), 'Administrative review');
        $this->assertDatabaseCount('delivery_disputes', 1);
    }

    public function test_failed_release_rolls_back_both_ledger_entries_without_undoing_delivery(): void
    {
        [$delivery] = $this->deliveredSale();
        $this->setSettlementSetting('settlement.auto_release_enabled', '1');
        $this->travelTo($delivery->settlement_due_at);
        $this->mock(AuditLogger::class, function ($mock) {
            $mock->shouldReceive('record')->once()->andThrow(new \RuntimeException('Audit failure'));
        });
        $this->assertThrows(fn () => app(MerchantLedgerService::class)->releaseDeliveredSale($delivery), \RuntimeException::class);
        $this->assertDatabaseCount('ledger_entries', 1);
        $this->assertDatabaseCount('delivery_proofs', 1);
        $this->assertSame(DeliveryStatus::Delivered, $delivery->fresh()->status);
        $this->assertNull($delivery->fresh()->settled_at);
    }

    public function test_adjusted_order_balance_blocks_dispute_hold_and_release_without_borrowing_other_funds(): void
    {
        [$delivery] = $this->deliveredSale();
        foreach ([[-100, $delivery->merchant_order_id], [10000, null]] as $index => [$amount, $merchantOrderId]) {
            LedgerEntry::query()->create([
                'merchant_id' => $delivery->merchantOrder->merchant_id,
                'merchant_order_id' => $merchantOrderId,
                'entry_type' => LedgerEntryType::Adjustment,
                'direction' => $amount > 0 ? 'credit' : 'debit',
                'amount' => $amount, 'net_amount' => $amount, 'status' => 'pending', 'currency' => 'ILS',
                'reference' => 'adjustment-'.$index, 'idempotency_key' => 'adjustment-'.$index, 'created_at' => now(),
            ]);
        }
        $this->assertThrows(fn () => app(DeliveryDisputeService::class)->open(
            $delivery, $delivery->order->user, 'Balance mismatch complaint',
        ), ValidationException::class);
        $this->assertDatabaseCount('delivery_disputes', 0);
        $this->setSettlementSetting('settlement.auto_release_enabled', '1');
        $this->travelTo($delivery->settlement_due_at);
        $this->artisan('settlements:release-due')->assertFailed();
        $this->assertDatabaseCount('ledger_entries', 3);
        $this->assertNull($delivery->fresh()->settled_at);
        $this->assertSame(0, (int) LedgerEntry::query()->where('status', 'available')->sum('net_amount'));
    }

    public function test_historical_delivery_without_deadline_remains_ineligible(): void
    {
        [$delivery] = $this->deliveredSale();
        $delivery->update(['settlement_due_at' => null, 'settlement_hold_days' => null]);
        $this->setSettlementSetting('settlement.auto_release_enabled', '1');
        $this->travel(30)->days();
        $this->artisan('settlements:release-due')->assertSuccessful();
        $this->assertNull($delivery->fresh()->settled_at);
        $this->assertDatabaseCount('ledger_entries', 1);
        $this->assertThrows(fn () => app(MerchantLedgerService::class)->releaseDeliveredSale($delivery), ValidationException::class);
    }

    public function test_delivery_assignment_enforces_non_empty_worker_location_scope(): void
    {
        $admin = $this->admin();
        $worker = $this->worker();
        $merchantOrder = $this->merchantOrder(OrderPaymentStatus::Paid);
        $outside = Location::query()->whereKeyNot($merchantOrder->origin_location_id)->firstOrFail();
        $worker->staffLocations()->sync([$outside->id]);

        $this->actingAs($admin);
        $this->assertThrows(
            fn () => app(DeliveryAssignmentService::class)
                ->assign($merchantOrder, $worker, $admin, null, 0),
            ValidationException::class,
        );
        $this->assertDatabaseCount('deliveries', 0);

        $worker->staffLocations()->sync([$merchantOrder->origin_location_id]);
        $delivery = app(DeliveryAssignmentService::class)
            ->assign($merchantOrder, $worker, $admin, null, 0);
        $this->assertSame($worker->id, $delivery->delivery_worker_id);
    }

    public function test_admin_can_unassign_and_then_assign_the_preserved_task_again(): void
    {
        $admin = $this->admin();
        $first = $this->worker();
        $second = $this->worker();
        $merchantOrder = $this->merchantOrder(OrderPaymentStatus::Paid);
        $delivery = app(DeliveryAssignmentService::class)->assign($merchantOrder, $first, $admin, null, 0);

        $this->actingAs($admin)->post(route('admin.deliveries.unassign', $delivery), [
            'lock_version' => 0, 'reason' => 'Worker reported an unavailable vehicle',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $delivery->refresh();
        $this->assertSame(DeliveryStatus::Unassigned, $delivery->status);
        $this->assertNull($delivery->delivery_worker_id);
        $this->assertSame('unassigned', $delivery->events()->latest('id')->value('event_type'));

        app(DeliveryAssignmentService::class)->assign($merchantOrder, $second, $admin, 'Replacement worker', 1);
        $delivery->refresh();
        $this->assertSame(DeliveryStatus::Assigned, $delivery->status);
        $this->assertSame($second->id, $delivery->delivery_worker_id);
        $this->assertDatabaseCount('deliveries', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'delivery.unassigned']);
    }

    public function test_worker_availability_controls_new_assignments_and_suspension_is_admin_only(): void
    {
        $admin = $this->admin();
        $worker = $this->worker();
        $this->actingAs($worker)->patch(route('delivery.tasks.availability'), [
            'availability' => DeliveryWorkerAvailability::Break->value,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(DeliveryWorkerAvailability::Break, DeliveryWorkerProfile::where('user_id', $worker->id)->firstOrFail()->availability);

        $this->actingAs($admin)->post(route('admin.deliveries.assign', $this->merchantOrder(OrderPaymentStatus::Paid)), [
            'delivery_worker_id' => $worker->id, 'lock_version' => 0,
        ])->assertSessionHasErrors('delivery_worker_id');

        $this->actingAs($worker)->patch(route('delivery.tasks.availability'), [
            'availability' => DeliveryWorkerAvailability::Suspended->value,
        ])->assertSessionHasErrors('availability');
        $this->actingAs($admin)->patch(route('admin.deliveries.workers.availability', $worker), [
            'availability' => DeliveryWorkerAvailability::Suspended->value, 'reason' => 'Administrative safety review',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(DeliveryWorkerAvailability::Suspended, $worker->deliveryWorkerProfile()->firstOrFail()->availability);
    }

    public function test_customer_rates_delivered_experience_once_and_rating_is_immutable(): void
    {
        [$delivery] = $this->deliveredSale();
        $owner = $delivery->order->user;
        $other = User::factory()->create();

        $this->actingAs($other)->post(route('delivery-ratings.store', $delivery), [
            'rating' => 1, 'comment' => 'Not this customer delivery',
        ])->assertForbidden();
        $this->actingAs($owner)->post(route('delivery-ratings.store', $delivery), [
            'rating' => 4, 'comment' => 'Delivery was professional',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('delivery-ratings.store', $delivery), [
            'rating' => 5,
        ])->assertSessionHasErrors('rating');

        $rating = DeliveryRating::query()->firstOrFail();
        $this->assertSame(4, $rating->rating);
        $this->assertThrows(fn () => $rating->update(['rating' => 1]), LogicException::class);
        $this->assertThrows(fn () => $rating->delete(), LogicException::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'delivery.rated']);
        $this->assertStringNotContainsString('Delivery was professional', AuditLog::query()->where('action', 'delivery.rated')->firstOrFail()->toJson());
    }

    public function test_customer_cannot_rate_before_delivery(): void
    {
        $admin = $this->admin();
        $worker = $this->worker();
        $delivery = app(DeliveryAssignmentService::class)->assign($this->merchantOrder(OrderPaymentStatus::Paid), $worker, $admin, null, 0);

        $this->actingAs($delivery->order->user)->post(route('delivery-ratings.store', $delivery), ['rating' => 5])
            ->assertSessionHasErrors('rating');
        $this->assertDatabaseCount('delivery_ratings', 0);
    }

    private function setSettlementSetting(string $key, string $value): void
    {
        MarketplaceSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    private function deliveredSale(): array
    {
        Storage::fake('local');
        [$delivery, $worker] = $this->inTransitDelivery();
        LedgerEntry::query()->create([
            'merchant_id' => $delivery->merchantOrder->merchant_id,
            'order_id' => $delivery->order_id, 'merchant_order_id' => $delivery->merchant_order_id,
            'entry_type' => LedgerEntryType::Sale, 'direction' => 'credit',
            'amount' => 9500, 'net_amount' => 9500, 'gross_amount' => 10000, 'commission_amount' => 500,
            'status' => LedgerStatus::Pending, 'currency' => 'ILS',
            'reference' => 'sale-'.$delivery->id, 'idempotency_key' => 'sale-'.$delivery->id, 'created_at' => now(),
        ]);
        $delivery = app(DeliveryConfirmationService::class)->confirmDelivered(
            $delivery, $worker, 3, $delivery->confirmation_pin,
            UploadedFile::fake()->create('proof.jpg', 20, 'image/jpeg'), null,
        );

        return [$delivery, $worker];
    }

    private function inTransitDelivery(): array
    {
        $admin = $this->admin();
        $worker = $this->worker();
        $this->actingAs($admin);
        $assignment = app(DeliveryAssignmentService::class);
        $delivery = $assignment->assign($this->merchantOrder(OrderPaymentStatus::Paid), $worker, $admin, null, 0);
        $this->actingAs($worker);
        $delivery = $assignment->accept($delivery, $worker, 0);
        $confirmation = app(DeliveryConfirmationService::class);
        $delivery = $confirmation->markPickedUp($delivery, $worker, 1, null);
        $delivery = $confirmation->markInTransit($delivery, $worker, 2, null);

        return [$delivery, $worker];
    }

    private function merchantOrder(OrderPaymentStatus $paymentStatus): MerchantOrder
    {
        $customer = User::factory()->create();
        $merchant = $this->merchant();
        $order = Order::query()->create([
            'user_id' => $customer->id,
            'subtotal' => 100,
            'shipping_fee' => 20,
            'total' => 120,
            'currency' => 'ILS',
            'delivery_address_snapshot' => [
                'recipient_name' => 'Delivery Customer',
                'governorate' => 'Gaza',
                'city' => 'Gaza City',
                'address' => 'Customer delivery street',
                'mobile' => '0599000999',
                'captured_at' => now()->toIso8601String(),
            ],
            'status' => OrderStatus::Confirmed,
            'payment_method' => 'manual_transfer',
            'payment_status' => $paymentStatus,
        ]);

        return MerchantOrder::query()->create([
            'order_id' => $order->id,
            'merchant_id' => $merchant->id,
            'group_key' => 'merchant:'.$merchant->id,
            'status' => MerchantOrderStatus::Confirmed,
            'product_subtotal' => 100,
            'delivery_fee' => 20,
            'service_fee' => 0,
            'commission_amount' => 5,
            'total' => 120,
            'currency' => 'ILS',
            'origin_location_id' => $merchant->location_id,
            'commission_snapshot' => ['amount' => '5.00'],
            'delivery_snapshot' => ['allocated_fee' => '20.00'],
            'confirmed_at' => now(),
        ]);
    }

    private function checkout(User $customer, ProductOffer $offer): Order
    {
        $this->actingAs($customer)
            ->withSession(['cart.items' => $this->cart($offer)])
            ->post(route('checkout.confirm'), ['payment_method' => 'manual_transfer'])
            ->assertRedirect();

        return Order::query()->latest('id')->firstOrFail();
    }

    private function cart(ProductOffer $offer): array
    {
        return ['delivery-row' => [
            'rowId' => 'delivery-row',
            'id' => $offer->product_id,
            'product_id' => $offer->product_id,
            'offer_id' => $offer->id,
            'slug' => $offer->product->slug,
            'name' => 'Untrusted name',
            'price' => 0.01,
            'unit' => 0.01,
            'qty' => 1,
            'options' => [],
        ]];
    }

    private function offer(string $name, float $price): array
    {
        $merchant = $this->merchant();
        $product = Product::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'price' => $price,
            'status' => ProductStatus::Active,
            'source' => 'merchant_submission',
            'source_key' => 'delivery-test-'.Str::uuid(),
            'in_stock' => true,
        ]);
        $offer = ProductOffer::query()->create([
            'product_id' => $product->id,
            'merchant_id' => $merchant->id,
            'location_id' => $merchant->location_id,
            'price' => $price,
            'stock' => 10,
            'status' => ProductOfferStatus::Active,
            'currency' => 'ILS',
            'source' => 'merchant',
            'source_key' => 'delivery-test-'.Str::uuid(),
            'last_confirmed_at' => now(),
        ]);

        return [$merchant, $offer];
    }

    private function merchant(): Merchant
    {
        $user = User::factory()->create();

        return Merchant::query()->create([
            'user_id' => $user->id,
            'location_id' => Location::query()->firstOrFail()->id,
            'legal_name' => 'Delivery Merchant '.$user->id,
            'identity_number' => 'DELIVERY-'.$user->id.'-'.fake()->unique()->numerify('######'),
            'phone' => '0569'.fake()->unique()->numerify('######'),
            'date_of_birth' => now()->subYears(30),
            'address' => 'Merchant pickup address '.$user->id,
            'business_type' => 'Retail',
            'verification_status' => MerchantVerificationStatus::Verified,
        ]);
    }

    private function worker(): User
    {
        $worker = User::factory()->create();
        $worker->assignRole('delivery-worker');

        return $worker;
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }
}

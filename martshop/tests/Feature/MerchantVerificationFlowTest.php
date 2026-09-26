<?php

namespace Tests\Feature;

use App\Enums\MerchantDocumentStatus;
use App\Enums\MerchantVerificationStatus;
use App\Models\Location;
use App\Models\Merchant;
use App\Models\User;
use App\Services\MerchantDocumentStorage;
use App\Services\MerchantRegistrationService;
use App\Services\MerchantVerificationService;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MerchantVerificationFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AuthorizationSeeder::class, LocationSeeder::class]);
        Storage::fake('local');
        $this->customer = User::factory()->create();
        $this->location = Location::firstOrFail();
    }

    public function test_customer_creates_a_merchant_profile_and_receives_merchant_role(): void
    {
        $this->actingAs($this->customer)
            ->put(route('merchant.profile.update'), $this->profileData())
            ->assertRedirect(route('merchant.profile.edit'));

        $merchant = $this->customer->fresh()->merchant;
        $this->assertNotNull($merchant);
        $this->assertTrue($this->customer->fresh()->hasRole('merchant'));
        $this->assertSame(MerchantVerificationStatus::Incomplete, $merchant->verification_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'merchant.profile_created', 'subject_id' => $merchant->id]);
    }

    public function test_merchant_cannot_submit_without_required_documents(): void
    {
        $this->createProfile();

        $this->actingAs($this->customer)
            ->post(route('merchant.submit'), ['current_password' => 'password'])
            ->assertSessionHasErrors('documents');

        $this->assertSame(MerchantVerificationStatus::Incomplete, $this->customer->merchant->fresh()->verification_status);
    }

    public function test_complete_kyc_flow_from_upload_to_admin_approval(): void
    {
        $merchant = $this->createProfile();

        foreach (['identity_front', 'identity_back', 'personal_photo'] as $type) {
            $this->actingAs($this->customer)->post(route('merchant.documents.store'), [
                'type' => $type,
                'document' => UploadedFile::fake()->create($type.'.jpg', 100, 'image/jpeg'),
                'current_password' => 'password',
            ])->assertRedirect(route('merchant.profile.edit'));
        }

        $this->actingAs($this->customer)->post(route('merchant.submit'), ['current_password' => 'password'])
            ->assertRedirect(route('merchant.profile.edit'));
        $this->assertSame(MerchantVerificationStatus::PendingReview, $merchant->fresh()->verification_status);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        foreach ($merchant->documents()->get() as $document) {
            $this->actingAs($admin)->patch(route('admin.merchants.documents.review', $document), [
                'status' => MerchantDocumentStatus::Accepted->value,
                'current_password' => 'password',
            ])->assertRedirect();
        }

        $this->actingAs($admin)->post(route('admin.merchants.approve', $merchant), [
            'current_password' => 'password',
        ])->assertRedirect();
        $this->assertSame(MerchantVerificationStatus::Verified, $merchant->fresh()->verification_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'merchant.verified', 'subject_id' => $merchant->id]);
    }

    public function test_customer_cannot_download_another_merchants_private_document(): void
    {
        $merchant = $this->createProfile();
        $this->actingAs($this->customer)->post(route('merchant.documents.store'), [
            'type' => 'identity_front',
            'document' => UploadedFile::fake()->create('identity.jpg', 100, 'image/jpeg'),
            'current_password' => 'password',
        ]);
        $document = $merchant->documents()->firstOrFail();

        $other = User::factory()->create();
        $this->actingAs($other)->get(route('merchant.documents.show', $document))->assertForbidden();
        $download = $this->actingAs($this->customer)->get(route('merchant.documents.show', $document))
            ->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $download->headers->get('Cache-Control'));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'merchant.document_viewed',
            'subject_id' => $document->id,
        ]);
    }

    public function test_private_document_download_fails_closed_for_tampered_metadata_or_content(): void
    {
        $merchant = $this->createProfile();
        $this->actingAs($this->customer)->post(route('merchant.documents.store'), [
            'type' => 'identity_front',
            'document' => UploadedFile::fake()->createWithContent('identity.pdf', '%PDF-1.4 private identity bytes'),
            'current_password' => 'password',
        ]);
        $document = $merchant->documents()->firstOrFail();
        $path = $document->getRawOriginal('path');
        $contents = Storage::disk('local')->get($path);

        $document->update(['size' => $document->size + 1]);
        $this->actingAs($this->customer)->get(route('merchant.documents.show', $document))->assertNotFound();
        $document->update(['size' => strlen($contents), 'sha256' => str_repeat('0', 64)]);
        $this->actingAs($this->customer)->get(route('merchant.documents.show', $document))->assertNotFound();
        $document->update(['sha256' => hash('sha256', $contents), 'path' => 'merchant-documents/'.$merchant->id.'/../outside.jpg']);
        $this->actingAs($this->customer)->get(route('merchant.documents.show', $document))->assertNotFound();
        $document->update(['path' => $path, 'disk' => 'public']);
        $this->actingAs($this->customer)->get(route('merchant.documents.show', $document))->assertNotFound();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'merchant.document_viewed']);
    }

    public function test_merchant_profile_and_admin_review_pages_render(): void
    {
        $merchant = $this->createProfile();
        $this->actingAs($this->customer)->get(route('merchant.profile.edit'))->assertOk();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin)->get(route('admin.merchants.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.merchants.show', $merchant))->assertOk();
    }

    public function test_merchant_profile_is_private_reauthenticated_minimized_and_service_authorized(): void
    {
        $merchant = $this->createProfile();

        $response = $this->actingAs($this->customer)
            ->get(route('merchant.profile.edit'))
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString("frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("object-src 'none'", $response->headers->get('Content-Security-Policy'));
        $profile = $response->viewData('merchant');
        foreach (['identity_number_hash', 'submitted_at', 'reviewed_at', 'reviewed_by'] as $column) {
            $this->assertArrayNotHasKey($column, $profile->getAttributes());
        }
        $this->assertEqualsCanonicalizing(
            ['id', 'name'],
            array_keys($response->viewData('locations')->firstOrFail()->getAttributes()),
        );

        $failed = $this->actingAs($this->customer)
            ->from(route('merchant.profile.edit'))
            ->put(route('merchant.profile.update'), array_merge($this->profileData(), [
                'legal_name' => 'PRIVATE LEGAL NAME',
                'identity_number' => 'PRIVATE-ID-999',
                'phone' => '0599111111',
                'date_of_birth' => '1991-02-03',
                'address' => 'PRIVATE ADDRESS',
                'business_type' => 'PRIVATE BUSINESS',
                'current_password' => 'wrong-password',
            ]))
            ->assertRedirect(route('merchant.profile.edit'))
            ->assertSessionHasErrors('current_password');
        foreach (['current_password', 'legal_name', 'identity_number', 'phone', 'date_of_birth', 'address', 'business_type'] as $key) {
            $this->assertArrayNotHasKey($key, $failed->getSession()->getOldInput());
        }
        $this->assertSame('متجر تجريبي قانوني', $merchant->fresh()->legal_name);

        $failedUpload = $this->actingAs($this->customer)
            ->post(route('merchant.documents.store'), [
                'type' => 'identity_front',
                'document' => UploadedFile::fake()->create('private-id.jpg', 100, 'image/jpeg'),
                'current_password' => 'wrong-password',
            ])
            ->assertSessionHasErrors('current_password');
        $this->assertArrayNotHasKey('current_password', $failedUpload->getSession()->getOldInput());
        $this->assertDatabaseCount('merchant_documents', 0);

        $other = User::factory()->create();
        $this->assertThrows(
            fn () => app(MerchantRegistrationService::class)->submit($merchant, $other),
            HttpException::class,
        );
        $this->assertThrows(
            fn () => app(MerchantDocumentStorage::class)->store(
                $merchant,
                \App\Enums\MerchantDocumentType::IdentityFront,
                UploadedFile::fake()->create('service-bypass.jpg', 100, 'image/jpeg'),
                $other,
            ),
            HttpException::class,
        );
        $this->assertDatabaseCount('merchant_documents', 0);

        $merchant->update(['verification_status' => MerchantVerificationStatus::PendingReview]);
        $this->assertThrows(
            fn () => app(MerchantRegistrationService::class)->saveProfile($this->customer, [
                'legal_name' => 'Service bypass',
            ]),
            HttpException::class,
        );
        $this->assertSame('متجر تجريبي قانوني', $merchant->fresh()->legal_name);
        $serialized = $merchant->fresh()->toArray();
        foreach (['identity_number', 'identity_number_hash', 'phone', 'date_of_birth', 'address', 'review_notes'] as $key) {
            $this->assertArrayNotHasKey($key, $serialized);
        }
    }

    public function test_kyc_review_is_private_reauthenticated_minimized_and_cannot_be_self_reviewed(): void
    {
        $merchant = $this->createProfile();
        $this->actingAs($this->customer)->post(route('merchant.documents.store'), [
            'type' => 'identity_front',
            'document' => UploadedFile::fake()->create('identity.jpg', 100, 'image/jpeg'),
            'current_password' => 'password',
        ]);
        $document = $merchant->documents()->firstOrFail();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $index = $this->actingAs($admin)->get(route('admin.merchants.index'))
            ->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString('no-store', $index->headers->get('Cache-Control'));
        $this->assertStringContainsString("default-src 'none'", $index->headers->get('Content-Security-Policy'));
        $index->assertViewHas('merchants', function ($merchants) {
            $attributes = $merchants->firstOrFail()->getAttributes();

            return collect(['identity_number', 'identity_number_hash', 'phone', 'date_of_birth', 'address', 'business_type'])
                ->every(fn ($column) => ! array_key_exists($column, $attributes));
        });
        $this->actingAs($admin)->get(route('admin.merchants.show', $merchant))
            ->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');

        $failed = $this->actingAs($admin)
            ->from(route('admin.merchants.show', $merchant))
            ->patch(route('admin.merchants.documents.review', $document), [
                'status' => MerchantDocumentStatus::Rejected->value,
                'review_notes' => 'PRIVATE REVIEW NOTES',
                'current_password' => 'wrong-password',
            ])->assertRedirect(route('admin.merchants.show', $merchant))
            ->assertSessionHasErrors('current_password');
        $oldInput = $failed->getSession()->getOldInput();
        $this->assertArrayNotHasKey('current_password', $oldInput);
        $this->assertArrayNotHasKey('review_notes', $oldInput);
        $this->assertSame(MerchantDocumentStatus::Pending, $document->fresh()->status);

        $this->customer->assignRole('admin');
        $this->actingAs($this->customer)->post(route('admin.merchants.approve', $merchant), [
            'current_password' => 'password',
        ])->assertForbidden();
        $this->assertThrows(
            fn () => app(MerchantVerificationService::class)->requestChanges(
                $merchant,
                $this->customer,
                'Self review bypass',
            ),
            HttpException::class,
        );
        $this->assertSame(MerchantVerificationStatus::Incomplete, $merchant->fresh()->verification_status);
    }

    private function createProfile(): Merchant
    {
        $this->actingAs($this->customer)->put(route('merchant.profile.update'), $this->profileData());

        return $this->customer->fresh()->merchant;
    }

    private function profileData(): array
    {
        return [
            'legal_name' => 'متجر تجريبي قانوني',
            'identity_number' => 'ID-778899',
            'phone' => '0599000000',
            'date_of_birth' => '1990-01-01',
            'location_id' => $this->location->id,
            'address' => 'عنوان خاص للتاجر',
            'business_type' => 'retail',
            'current_password' => 'password',
        ];
    }
}

<?php

namespace Tests\Feature;

use App\Enums\MerchantVerificationStatus;
use App\Enums\ProductChangeRequestStatus;
use App\Enums\ProductOfferStatus;
use App\Enums\ProductStatus;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Location;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\ProductChangeRequest;
use App\Models\ProductOffer;
use App\Models\User;
use App\Services\ProductChangeRequestService;
use App\Services\ProductChangeRequestImageStorage;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\CategoryTreeSeeder;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ProductChangeRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AuthorizationSeeder::class, LocationSeeder::class, CategoryTreeSeeder::class]);
    }

    public function test_published_product_stays_unchanged_until_change_request_is_approved(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        [$merchant, $product] = $this->publishedMerchantProduct();
        $admin = $this->admin();
        $customer = User::factory()->create();
        $newCategory = Category::query()->where('path', 'books/education')->firstOrFail();
        $originalSlug = $product->slug;

        $this->actingAs($merchant->user)
            ->post(route('merchant.catalog.change-requests.store', $product), [
                'name' => 'Approved Canonical Name',
                'category_id' => $newCategory->id,
                'description' => 'New approved description',
                'model' => 'MODEL-2',
                'specifications' => json_encode(['storage' => '256GB']),
                'image' => $this->fakeImage('proposal.png'),
            ])
            ->assertRedirect(route('merchant.catalog.index'));

        $changeRequest = ProductChangeRequest::query()->firstOrFail();
        $this->assertSame(ProductChangeRequestStatus::PendingReview, $changeRequest->status);
        $this->assertSame('Original Canonical Name', $product->fresh()->name);
        $this->get('/p/'.$originalSlug)->assertOk()->assertSee('Original Canonical Name');
        Storage::disk('local')->assertExists($changeRequest->proposed_image_path);
        $storedImage = Storage::disk('local')->get($changeRequest->proposed_image_path);
        $this->assertMatchesRegularExpression(
            '/\Aproduct-change-requests\/'.$merchant->id.'\/[a-f0-9-]{36}\.png\z/',
            $changeRequest->proposed_image_path,
        );
        $this->assertSame(strlen($storedImage), $changeRequest->proposed_image_size);
        $this->assertSame(hash('sha256', $storedImage), $changeRequest->proposed_image_sha256);
        $submissionAudit = AuditLog::query()->where('action', 'catalog.product_change_submitted')->sole();
        $this->assertArrayNotHasKey('proposed_image_path', $submissionAudit->after);
        $this->actingAs($merchant->user)->get(route('merchant.catalog.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.catalog.index'))->assertOk();

        $this->actingAs($customer)
            ->get(route('product-change-requests.image', $changeRequest))
            ->assertForbidden();
        $imageResponse = $this->actingAs($admin)
            ->get(route('product-change-requests.image', $changeRequest))
            ->assertOk();
        $this->assertSame('application/octet-stream', $imageResponse->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', $imageResponse->headers->get('Cache-Control'));
        $this->assertStringContainsString(
            'product-change-image-'.$changeRequest->id.'.png',
            $imageResponse->headers->get('Content-Disposition'),
        );
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'catalog.product_change_image_viewed',
            'subject_id' => $changeRequest->id,
            'actor_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.catalog.change-requests.update', $changeRequest), [
                'decision' => 'approved',
                'current_password' => 'password',
            ])
            ->assertRedirect();

        $product->refresh();
        $changeRequest->refresh();
        $this->assertSame(ProductChangeRequestStatus::Approved, $changeRequest->status);
        $this->assertNull($changeRequest->open_key);
        $this->assertSame('Approved Canonical Name', $product->name);
        $this->assertSame($originalSlug, $product->slug);
        $this->assertSame($newCategory->id, $product->category_id);
        $this->assertSame(['storage' => '256GB'], $product->specifications);
        $this->assertTrue(str_starts_with($product->image, 'storage/products/'));
        Storage::disk('public')->assertExists(str_replace('storage/', '', $product->image));
        $this->assertTrue(AuditLog::query()->where('action', 'catalog.product_core_updated')->exists());
    }

    public function test_proposed_product_image_rejects_tampered_metadata_and_is_not_approved(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        [$merchant, $product] = $this->publishedMerchantProduct();
        $admin = $this->admin();
        $category = Category::query()->where('path', 'electronics/phones')->firstOrFail();

        $this->actingAs($merchant->user)->post(route('merchant.catalog.change-requests.store', $product), [
            'name' => 'Integrity Checked Name',
            'category_id' => $category->id,
            'image' => $this->fakeImage('integrity.png'),
        ])->assertRedirect(route('merchant.catalog.index'));

        $changeRequest = ProductChangeRequest::query()->firstOrFail();
        $originalPath = $changeRequest->proposed_image_path;
        $originalSize = $changeRequest->proposed_image_size;
        $originalHash = $changeRequest->proposed_image_sha256;

        $this->actingAs($merchant->user)
            ->get(route('product-change-requests.image', $changeRequest))
            ->assertOk();
        $this->assertSame(1, AuditLog::query()->where('action', 'catalog.product_change_image_viewed')->count());

        $changeRequest->update(['proposed_image_size' => $originalSize + 1]);
        $this->actingAs($admin)->get(route('product-change-requests.image', $changeRequest->fresh()))->assertNotFound();

        $changeRequest->update([
            'proposed_image_size' => $originalSize,
            'proposed_image_sha256' => str_repeat('0', 64),
        ]);
        $this->actingAs($admin)->get(route('product-change-requests.image', $changeRequest->fresh()))->assertNotFound();

        $changeRequest->update([
            'proposed_image_sha256' => $originalHash,
            'proposed_image_path' => 'product-change-requests/'.$merchant->id.'/../outside.png',
        ]);
        $this->actingAs($admin)->get(route('product-change-requests.image', $changeRequest->fresh()))->assertNotFound();

        $changeRequest->update(['proposed_image_path' => $originalPath, 'proposed_image_disk' => 'public']);
        $this->actingAs($admin)->get(route('product-change-requests.image', $changeRequest->fresh()))->assertNotFound();
        $this->assertSame(1, AuditLog::query()->where('action', 'catalog.product_change_image_viewed')->count());

        $changeRequest->update([
            'proposed_image_disk' => 'local',
            'proposed_image_sha256' => str_repeat('0', 64),
        ]);
        $this->actingAs($admin)
            ->patch(route('admin.catalog.change-requests.update', $changeRequest->fresh()), [
                'decision' => 'approved',
                'current_password' => 'password',
            ])
            ->assertSessionHasErrors('decision');

        $this->assertSame(ProductChangeRequestStatus::PendingReview, $changeRequest->fresh()->status);
        $this->assertSame('Original Canonical Name', $product->fresh()->name);
        $this->assertSame([], Storage::disk('public')->allFiles('products'));
    }

    public function test_image_storage_rejects_unsafe_or_oversized_files_even_when_called_directly(): void
    {
        Storage::fake('local');
        $merchant = $this->merchant(MerchantVerificationStatus::Verified);
        $storage = app(ProductChangeRequestImageStorage::class);

        foreach ([
            UploadedFile::fake()->create('payload.php', 1, 'text/x-php'),
            UploadedFile::fake()->create('oversized.jpg', 5121, 'image/jpeg'),
        ] as $file) {
            try {
                $storage->store($merchant, $file);
                $this->fail('Unsafe product change image was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('image', $exception->errors());
            }
        }

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_requested_changes_can_be_corrected_and_resubmitted_once(): void
    {
        Storage::fake('local');
        [$merchant, $product] = $this->publishedMerchantProduct();
        $admin = $this->admin();
        $category = Category::query()->where('path', 'electronics/phones')->firstOrFail();

        $payload = [
            'name' => 'First Proposal',
            'category_id' => $category->id,
            'description' => 'First description',
            'image' => $this->fakeImage('first.png'),
        ];
        $this->actingAs($merchant->user)
            ->post(route('merchant.catalog.change-requests.store', $product), $payload);
        $changeRequest = ProductChangeRequest::query()->firstOrFail();
        $firstImagePath = $changeRequest->proposed_image_path;

        $this->actingAs($merchant->user)
            ->from(route('merchant.catalog.change-requests.create', $product))
            ->post(route('merchant.catalog.change-requests.store', $product), $payload)
            ->assertSessionHasErrors('change_request');
        $this->assertDatabaseCount('product_change_requests', 1);

        $this->actingAs($admin)->patch(route('admin.catalog.change-requests.update', $changeRequest), [
            'decision' => 'changes_requested',
            'reason' => 'صحح الاسم',
            'current_password' => 'password',
        ]);
        $changeRequest->refresh();
        $this->assertSame(ProductChangeRequestStatus::ChangesRequested, $changeRequest->status);
        $this->actingAs($merchant->user)
            ->get(route('merchant.catalog.change-requests.create', $product))
            ->assertOk();

        $this->actingAs($merchant->user)
            ->post(route('merchant.catalog.change-requests.store', $product), [
                'change_request_lock_version' => $changeRequest->lock_version,
                'name' => 'Corrected Proposal',
                'category_id' => $category->id,
                'description' => 'Corrected description',
                'image' => $this->fakeImage('corrected.png'),
            ])
            ->assertRedirect(route('merchant.catalog.index'));

        $changeRequest->refresh();
        $this->assertSame(ProductChangeRequestStatus::PendingReview, $changeRequest->status);
        $this->assertSame('Corrected Proposal', $changeRequest->proposed_changes['name']);
        $this->assertNull($changeRequest->review_notes);
        $this->assertSame($product->id.':'.$merchant->id, $changeRequest->open_key);
        $this->assertNotSame($firstImagePath, $changeRequest->proposed_image_path);
        Storage::disk('local')->assertMissing($firstImagePath);
        Storage::disk('local')->assertExists($changeRequest->proposed_image_path);
        $this->assertTrue(AuditLog::query()->where('action', 'catalog.product_change_resubmitted')->exists());
    }

    public function test_new_unpublished_product_can_be_corrected_only_by_its_owner(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $merchant = $this->merchant(MerchantVerificationStatus::Verified);
        $otherMerchant = $this->merchant(MerchantVerificationStatus::Verified);
        $admin = $this->admin();
        $oldCategory = Category::query()->where('path', 'electronics/phones')->firstOrFail();
        $newCategory = Category::query()->where('path', 'electronics/computing')->firstOrFail();

        $this->actingAs($merchant->user)->post('/merchant/catalog', [
            'product_mode' => 'new',
            'name' => 'Draft Merchant Product',
            'category_id' => $oldCategory->id,
            'description' => 'Needs correction',
            'image' => $this->fakeImage('draft.png'),
            'price' => 50,
            'stock' => 3,
            'location_id' => Location::query()->firstOrFail()->id,
            'preparation_time_days' => 1,
        ]);
        $product = Product::query()->where('name', 'Draft Merchant Product')->firstOrFail();
        $offer = $product->offers()->firstOrFail();
        $originalSubmissionImage = $product->submission_image_path;

        $this->actingAs($admin)->patch(route('admin.catalog.products.update', $product), [
            'decision' => 'changes_requested',
            'reason' => 'غيّر الاسم والتصنيف',
            'current_password' => 'password',
        ]);
        $this->assertSame(ProductStatus::ChangesRequested, $product->fresh()->status);
        $this->assertSame(ProductOfferStatus::ChangesRequested, $offer->fresh()->status);

        $this->actingAs($otherMerchant->user)
            ->get(route('merchant.catalog.products.edit', $product))
            ->assertForbidden();

        $this->actingAs($merchant->user)
            ->get(route('merchant.catalog.products.edit', $product))
            ->assertOk();

        $this->actingAs($merchant->user)
            ->patch(route('merchant.catalog.products.update', $product), [
                'name' => 'Corrected Draft Product',
                'category_id' => $newCategory->id,
                'description' => 'Corrected draft',
                'image' => $this->fakeImage('corrected-draft.png'),
            ])
            ->assertRedirect(route('merchant.catalog.index'));

        $product->refresh();
        $this->assertSame(ProductStatus::PendingReview, $product->status);
        $this->assertSame('Corrected Draft Product', $product->name);
        $this->assertSame($newCategory->id, $product->category_id);
        $this->assertNull($product->review_notes);
        $this->assertNotSame($originalSubmissionImage, $product->submission_image_path);
        Storage::disk('local')->assertMissing($originalSubmissionImage);
        Storage::disk('local')->assertExists($product->submission_image_path);
        $this->assertTrue($product->categories()->whereKey($newCategory->id)->exists());
        $this->assertFalse($product->categories()->whereKey($oldCategory->id)->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'catalog.product_resubmitted')->exists());
    }

    public function test_merchant_without_an_offer_cannot_propose_product_changes(): void
    {
        $merchant = $this->merchant(MerchantVerificationStatus::Verified);
        $product = $this->product();

        $this->actingAs($merchant->user)
            ->get(route('merchant.catalog.change-requests.create', $product))
            ->assertForbidden();
    }

    public function test_merchant_moderator_cannot_review_own_product_change_request(): void
    {
        [$merchant, $product] = $this->publishedMerchantProduct();
        $category = Category::query()->where('path', 'electronics/phones')->firstOrFail();
        $this->actingAs($merchant->user)->post(route('merchant.catalog.change-requests.store', $product), [
            'name' => 'Self Reviewed Change',
            'category_id' => $category->id,
        ])->assertRedirect(route('merchant.catalog.index'));
        $changeRequest = ProductChangeRequest::query()->firstOrFail();
        $merchant->user->assignRole('admin');

        $this->actingAs($merchant->user)->get(route('admin.catalog.index'))
            ->assertOk()
            ->assertViewHas(
                'changeRequests',
                fn ($requests) => ! $requests->contains('id', $changeRequest->id),
            );
        $this->actingAs($merchant->user)
            ->patch(route('admin.catalog.change-requests.update', $changeRequest), [
                'decision' => 'approved',
                'current_password' => 'password',
            ])
            ->assertForbidden();
        $this->assertThrows(
            fn () => app(ProductChangeRequestService::class)->review(
                $changeRequest,
                ProductChangeRequestStatus::Approved,
                $merchant->user,
                null,
            ),
            HttpException::class,
        );
        $this->assertSame(ProductChangeRequestStatus::PendingReview, $changeRequest->fresh()->status);
        $this->assertSame('Original Canonical Name', $product->fresh()->name);
    }

    private function publishedMerchantProduct(): array
    {
        $merchant = $this->merchant(MerchantVerificationStatus::Verified);
        $product = $this->product();
        ProductOffer::query()->create([
            'product_id' => $product->id,
            'merchant_id' => $merchant->id,
            'location_id' => Location::query()->firstOrFail()->id,
            'price' => 80,
            'stock' => 5,
            'status' => ProductOfferStatus::Active,
            'currency' => 'ILS',
            'source' => 'merchant',
            'source_key' => 'test:'.$merchant->id.':'.$product->id,
            'last_confirmed_at' => now(),
        ]);

        return [$merchant, $product];
    }

    private function product(): Product
    {
        $category = Category::query()->where('path', 'electronics/phones')->firstOrFail();
        $product = Product::query()->create([
            'name' => 'Original Canonical Name',
            'slug' => 'original-canonical-name',
            'category_id' => $category->id,
            'description' => 'Original description',
            'model' => 'MODEL-1',
            'price' => 100,
            'status' => ProductStatus::Active,
            'in_stock' => true,
        ]);
        $product->categories()->attach($category->id, ['is_primary' => true, 'sort_order' => 0]);

        return $product;
    }

    private function merchant(MerchantVerificationStatus $status): Merchant
    {
        $user = User::factory()->create();

        return Merchant::query()->create([
            'user_id' => $user->id,
            'location_id' => Location::query()->firstOrFail()->id,
            'legal_name' => 'Merchant '.$user->id,
            'identity_number' => 'PCR-'.$user->id.'-'.fake()->unique()->numerify('#####'),
            'phone' => '0599111'.$user->id,
            'date_of_birth' => now()->subYears(30),
            'address' => 'Address',
            'business_type' => 'Retail',
            'verification_status' => $status,
        ]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    private function fakeImage(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nAAAAABJRU5ErkJggg=='),
        );
    }
}

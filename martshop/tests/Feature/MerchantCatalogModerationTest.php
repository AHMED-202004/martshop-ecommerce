<?php

namespace Tests\Feature;

use App\Enums\MerchantVerificationStatus;
use App\Enums\ProductOfferStatus;
use App\Enums\ProductStatus;
use App\Models\AuditLog;
use App\Models\CatalogReviewDecision;
use App\Models\Category;
use App\Models\Location;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\User;
use App\Services\CatalogModerationService;
use App\Services\CatalogItemResolver;
use App\Services\MerchantCatalogService;
use App\Services\MerchantProductSubmissionService;
use App\Services\ProductChangeRequestService;
use App\Services\ProductOfferLifecycleService;
use App\Services\ProductSubmissionImageStorage;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\CategoryTreeSeeder;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MerchantCatalogModerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            AuthorizationSeeder::class,
            LocationSeeder::class,
            CategoryTreeSeeder::class,
        ]);
    }

    public function test_unverified_merchant_cannot_create_product_offers(): void
    {
        $merchant = $this->merchant(MerchantVerificationStatus::Incomplete);
        $product = $this->activeProduct();

        $this->actingAs($merchant->user)
            ->get('/merchant/catalog/create')
            ->assertForbidden();

        $this->actingAs($merchant->user)
            ->post('/merchant/catalog', $this->offerPayload($product->id))
            ->assertForbidden();

        $this->assertDatabaseCount('product_offers', 0);
    }

    public function test_product_image_storage_rejects_disguised_and_oversized_files_directly(): void
    {
        Storage::fake('local');
        $merchant = $this->merchant(MerchantVerificationStatus::Verified);
        $storage = app(ProductSubmissionImageStorage::class);
        $disguisedPath = tempnam(sys_get_temp_dir(), 'mart-product-image-');
        file_put_contents($disguisedPath, '<?php echo "unsafe";');

        try {
            $this->assertThrows(
                fn () => $storage->store($merchant, new UploadedFile(
                    $disguisedPath,
                    'script.jpg',
                    'image/jpeg',
                    null,
                    true,
                )),
                ValidationException::class,
            );
        } finally {
            @unlink($disguisedPath);
        }
        $this->assertThrows(
            fn () => $storage->store(
                $merchant,
                UploadedFile::fake()->create('oversized.jpg', 5121, 'image/jpeg'),
            ),
            ValidationException::class,
        );

        $this->assertSame([], Storage::disk('local')->allFiles('product-submissions'));
    }

    public function test_verified_merchant_can_submit_an_offer_for_an_existing_product(): void
    {
        $merchant = $this->merchant(MerchantVerificationStatus::Verified);
        $product = $this->activeProduct();

        $this->actingAs($merchant->user)
            ->post('/merchant/catalog', $this->offerPayload($product->id))
            ->assertRedirect(route('merchant.catalog.index'));

        $offer = ProductOffer::query()->firstOrFail();
        $this->assertSame($merchant->id, $offer->merchant_id);
        $this->assertSame($product->id, $offer->product_id);
        $this->assertSame(ProductOfferStatus::PendingReview, $offer->status);
        $this->assertSame('79.99', $offer->price);
        $sizes = $offer->variants->pluck('attributes')->pluck('size')->sort()->values()->all();
        $this->assertSame(['40', '41', '42'], $sizes);
        $this->assertTrue(AuditLog::query()->where('action', 'catalog.offer_submitted')->exists());

        $page = $this->actingAs($merchant->user)->get(route('merchant.catalog.index'))
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString('no-store', $page->headers->get('Cache-Control'));
        $page->assertViewHas('merchant', function ($viewMerchant) {
            return collect(['identity_number', 'identity_number_hash', 'phone', 'date_of_birth', 'address'])
                ->every(fn ($column) => ! array_key_exists($column, $viewMerchant->getAttributes()));
        });
        $page->assertViewHas('offers', function ($offers) {
            return collect(['compare_at_price', 'warranty', 'metadata', 'source', 'source_key', 'reviewed_by'])
                ->every(fn ($column) => ! array_key_exists($column, $offers->firstOrFail()->getAttributes()));
        });
    }

    public function test_new_product_and_offer_stay_hidden_until_both_are_approved(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $merchant = $this->merchant(MerchantVerificationStatus::Verified);
        $admin = $this->admin();
        $category = Category::query()->where('path', 'electronics/phones')->firstOrFail();

        $payload = $this->offerPayload();
        $payload = array_merge($payload, [
            'product_mode' => 'new',
            'name' => 'هاتف تجريبي للمراجعة',
            'category_id' => $category->id,
            'description' => 'وصف المنتج الجديد',
            'model' => 'TEST-100',
            'image' => UploadedFile::fake()->createWithContent(
                'phone.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nAAAAABJRU5ErkJggg=='),
            ),
        ]);

        $this->actingAs($merchant->user)
            ->post('/merchant/catalog', $payload)
            ->assertRedirect(route('merchant.catalog.index'));

        $product = Product::query()->where('name', 'هاتف تجريبي للمراجعة')->firstOrFail();
        $offer = $product->offers()->firstOrFail();
        $this->assertSame(ProductStatus::PendingReview, $product->status);
        $this->assertSame(ProductOfferStatus::PendingReview, $offer->status);
        $privateImagePath = $product->submission_image_path;
        $privateImage = Storage::disk('local')->get($privateImagePath);
        $this->assertNull($product->image);
        $this->assertSame('local', $product->submission_image_disk);
        $this->assertSame(strlen($privateImage), $product->submission_image_size);
        $this->assertSame(hash('sha256', $privateImage), $product->submission_image_sha256);
        Storage::disk('local')->assertExists($privateImagePath);
        $this->actingAs(User::factory()->create())
            ->get(route('product-submissions.image', $product))
            ->assertForbidden();
        $imageResponse = $this->actingAs($admin)
            ->get(route('product-submissions.image', $product))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $imageResponse->headers->get('Cache-Control'));
        Storage::disk('public')->assertDirectoryEmpty('products');
        $this->get('/p/'.$product->slug)->assertNotFound();

        $this->actingAs($admin)
            ->patch(route('admin.catalog.products.update', $product), [
                'decision' => 'active',
                'current_password' => 'password',
            ])
            ->assertRedirect();
        $product->refresh();
        $this->assertSame(ProductStatus::Active, $product->status);
        $this->assertNull($product->submission_image_path);
        Storage::disk('local')->assertMissing($privateImagePath);
        Storage::disk('public')->assertExists(str_replace('storage/', '', $product->image));
        $this->get('/p/'.$product->slug)->assertNotFound();

        $this->actingAs($admin)
            ->patch(route('admin.catalog.offers.update', $offer), [
                'decision' => 'active',
                'current_password' => 'password',
            ])
            ->assertRedirect();
        $offer->refresh();
        $this->assertSame(ProductOfferStatus::Active, $offer->status);
        $this->assertSame(2, CatalogReviewDecision::query()->where('reviewer_id', $admin->id)->count());
        $this->assertDatabaseHas('catalog_review_decisions', [
            'subject_type' => $product->getMorphClass(), 'subject_id' => $product->id,
            'from_status' => 'pending_review', 'to_status' => 'active', 'is_reversal' => false,
        ]);

        $this->get('/p/'.$product->slug)
            ->assertOk()
            ->assertSee('هاتف تجريبي للمراجعة')
            ->assertSee('79.99');
        $this->assertSame(5, AuditLog::query()->count());
    }

    public function test_long_product_names_generate_bounded_route_safe_slugs(): void
    {
        Storage::fake('local');
        $merchant = $this->merchant(MerchantVerificationStatus::Verified);
        $category = Category::query()->where('path', 'electronics/phones')->firstOrFail();
        $payload = array_merge($this->offerPayload(), [
            'product_mode' => 'new',
            'name' => str_repeat('a', 250),
            'category_id' => $category->id,
            'image' => UploadedFile::fake()->createWithContent(
                'long.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nAAAAABJRU5ErkJggg=='),
            ),
        ]);

        $this->actingAs($merchant->user)->post(route('merchant.catalog.store'), $payload)
            ->assertRedirect(route('merchant.catalog.index'));

        $slug = Product::query()->sole()->slug;
        $this->assertLessThanOrEqual(200, strlen($slug));
        $this->assertMatchesRegularExpression('/\A[A-Za-z0-9][A-Za-z0-9_-]{0,199}\z/', $slug);
        $this->get(route('product.show', $slug))->assertNotFound();
    }

    public function test_hidden_category_ancestors_are_rejected_by_merchant_forms_and_service(): void
    {
        Storage::fake('local');
        $merchant = $this->merchant(MerchantVerificationStatus::Verified);
        Category::query()->where('path', 'electronics/phones')->update(['status' => 'hidden']);
        $descendant = Category::query()->where('path', 'electronics/phones/samsung')->sole();

        $page = $this->actingAs($merchant->user)->get(route('merchant.catalog.create'))->assertOk();
        $this->assertFalse($page->viewData('categories')->contains('id', $descendant->id));

        $payload = array_merge($this->offerPayload(), [
            'product_mode' => 'new',
            'name' => 'Hidden category submission',
            'category_id' => $descendant->id,
        ]);
        $this->actingAs($merchant->user)->post(route('merchant.catalog.store'), $payload)
            ->assertSessionHasErrors('category_id');
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_offers', 0);

        $image = UploadedFile::fake()->createWithContent(
            'hidden.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nAAAAABJRU5ErkJggg=='),
        );
        $this->assertThrows(
            fn () => app(MerchantCatalogService::class)->submit($merchant, $merchant->user, $payload, $image),
            ValidationException::class,
        );
        $this->assertSame([], Storage::disk('local')->allFiles('product-submissions'));
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_offers', 0);
    }

    public function test_existing_products_beneath_hidden_categories_cannot_receive_new_offers(): void
    {
        $merchant = $this->merchant(MerchantVerificationStatus::Verified);
        Category::query()->where('path', 'electronics/phones')->update(['status' => 'hidden']);
        $category = Category::query()->where('path', 'electronics/phones/samsung')->sole();
        $product = $this->activeProduct();
        $product->categories()->attach($category);

        $page = $this->actingAs($merchant->user)->get(route('merchant.catalog.create'))->assertOk();
        $this->assertFalse($page->viewData('products')->contains('id', $product->id));

        $payload = $this->offerPayload($product->id);
        $this->actingAs($merchant->user)->post(route('merchant.catalog.store'), $payload)
            ->assertSessionHasErrors('product_id');
        $this->assertDatabaseCount('product_offers', 0);

        $this->assertThrows(
            fn () => app(MerchantCatalogService::class)->submit($merchant, $merchant->user, $payload, null),
            ValidationException::class,
        );
        $this->assertDatabaseCount('product_offers', 0);
    }

    public function test_customer_cannot_moderate_and_suspending_merchant_pauses_active_offers(): void
    {
        $merchant = $this->merchant(MerchantVerificationStatus::Verified);
        $admin = $this->admin();
        $customer = User::factory()->create();
        $product = $this->activeProduct();

        $this->actingAs($merchant->user)->post('/merchant/catalog', $this->offerPayload($product->id));
        $offer = ProductOffer::query()->firstOrFail();

        $this->actingAs($customer)
            ->patch(route('admin.catalog.offers.update', $offer), [
                'decision' => 'active',
                'current_password' => 'password',
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->patch(route('admin.catalog.offers.update', $offer), [
                'decision' => 'active',
                'current_password' => 'password',
            ])
            ->assertRedirect();
        $this->assertSame(ProductOfferStatus::Active, $offer->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.merchants.suspend', $merchant), [
                'reason' => 'مخالفة تشغيلية',
                'current_password' => 'password',
            ])
            ->assertRedirect();

        $this->assertSame(MerchantVerificationStatus::Suspended, $merchant->fresh()->verification_status);
        $this->assertSame(ProductOfferStatus::Paused, $offer->fresh()->status);
        $this->assertTrue(AuditLog::query()
            ->where('action', 'catalog.offer_paused')
            ->where('metadata->trigger', 'merchant_suspended')
            ->exists());
    }

    public function test_pending_product_image_fails_closed_when_storage_metadata_or_content_is_tampered(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $merchant = $this->merchant(MerchantVerificationStatus::Verified);
        $admin = $this->admin();
        $category = Category::query()->where('path', 'electronics/phones')->firstOrFail();
        $payload = array_merge($this->offerPayload(), [
            'name' => 'Integrity Protected Draft',
            'category_id' => $category->id,
            'image' => UploadedFile::fake()->createWithContent(
                'protected.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nAAAAABJRU5ErkJggg=='),
            ),
        ]);
        $this->actingAs($merchant->user)->post('/merchant/catalog', $payload);
        $product = Product::query()->where('name', 'Integrity Protected Draft')->firstOrFail();
        $path = $product->submission_image_path;
        $size = $product->submission_image_size;
        $sha256 = $product->submission_image_sha256;

        $this->actingAs($admin)->get(route('product-submissions.image', $product))->assertOk();
        $this->assertSame(1, AuditLog::query()->where('action', 'catalog.product_submission_image_viewed')->count());

        $product->update(['submission_image_size' => $size + 1]);
        $this->actingAs($admin)->get(route('product-submissions.image', $product->fresh()))->assertNotFound();
        $product->update([
            'submission_image_size' => $size,
            'submission_image_sha256' => str_repeat('0', 64),
        ]);
        $this->actingAs($admin)->get(route('product-submissions.image', $product->fresh()))->assertNotFound();
        $product->update([
            'submission_image_sha256' => $sha256,
            'submission_image_path' => 'product-submissions/'.$merchant->id.'/../outside.png',
        ]);
        $this->actingAs($admin)->get(route('product-submissions.image', $product->fresh()))->assertNotFound();
        $product->update(['submission_image_path' => $path, 'submission_image_disk' => 'public']);
        $this->actingAs($admin)->get(route('product-submissions.image', $product->fresh()))->assertNotFound();

        $product->update(['submission_image_disk' => 'local']);
        Storage::disk('local')->put($path, 'tampered image bytes');
        $this->actingAs($admin)->patch(route('admin.catalog.products.update', $product->fresh()), [
            'decision' => 'active',
            'current_password' => 'password',
        ])->assertSessionHasErrors('decision');

        $this->assertSame(ProductStatus::PendingReview, $product->fresh()->status);
        $this->assertNull($product->fresh()->image);
        Storage::disk('public')->assertDirectoryEmpty('products');
        $this->assertSame(1, AuditLog::query()->where('action', 'catalog.product_submission_image_viewed')->count());
    }

    public function test_catalog_services_cannot_impersonate_another_merchant_or_store_spoofed_images(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $merchant = $this->merchant(MerchantVerificationStatus::Verified);
        $attacker = User::factory()->create();
        $product = $this->activeProduct();
        $category = Category::query()->where('path', 'electronics/phones')->firstOrFail();
        $image = UploadedFile::fake()->createWithContent(
            'spoofed.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nAAAAABJRU5ErkJggg=='),
        );

        $this->assertThrows(
            fn () => app(MerchantCatalogService::class)->submit(
                $merchant,
                $attacker,
                array_merge($this->offerPayload(), ['name' => 'Spoofed', 'category_id' => $category->id]),
                $image,
            ),
            HttpException::class,
        );
        $this->assertSame([], Storage::disk('local')->allFiles('product-submissions'));

        $this->actingAs($merchant->user)->post('/merchant/catalog', $this->offerPayload($product->id));
        $offer = ProductOffer::query()->firstOrFail();
        $this->assertThrows(
            fn () => app(ProductOfferLifecycleService::class)->update(
                $offer,
                $merchant,
                $attacker,
                $this->updatePayload($offer, ['price' => 1]),
            ),
            HttpException::class,
        );

        $draft = Product::query()->create([
            'name' => 'Owned Draft',
            'slug' => 'owned-draft',
            'category_id' => $category->id,
            'created_by_merchant_id' => $merchant->id,
            'price' => 10,
            'status' => ProductStatus::ChangesRequested,
        ]);
        $this->assertThrows(
            fn () => app(MerchantProductSubmissionService::class)->resubmit(
                $draft,
                $merchant,
                $attacker,
                ['name' => 'Spoofed Draft', 'category_id' => $category->id],
                $image,
            ),
            HttpException::class,
        );
        $this->assertSame('Owned Draft', $draft->fresh()->name);

        $this->assertThrows(
            fn () => app(ProductChangeRequestService::class)->submit(
                $product,
                $merchant,
                $attacker,
                ['name' => 'Spoofed Change', 'category_id' => $category->id],
                $image,
            ),
            HttpException::class,
        );
        $this->assertDatabaseCount('product_change_requests', 0);
        $this->assertSame('79.99', $offer->fresh()->price);
        $this->assertSame([], Storage::disk('local')->allFiles('product-submissions'));
        $this->assertSame([], Storage::disk('local')->allFiles('product-change-requests'));
    }

    public function test_catalog_review_is_private_reauthenticated_minimized_and_service_authorized(): void
    {
        $merchant = $this->merchant(MerchantVerificationStatus::Verified);
        $admin = $this->admin();
        $customer = User::factory()->create();
        $product = $this->activeProduct();
        $this->actingAs($merchant->user)->post('/merchant/catalog', $this->offerPayload($product->id));
        $offer = ProductOffer::query()->firstOrFail();

        $page = $this->actingAs($admin)->get(route('admin.catalog.index'))
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertSee('صفحة مراجعة داخلية خاصة');
        $this->assertStringContainsString('no-store', $page->headers->get('Cache-Control'));
        $page->assertViewHas('offers', function ($offers) use ($offer) {
            $attributes = $offers->firstOrFail()->getAttributes();

            return $offers->contains('id', $offer->id)
                && collect(['compare_at_price', 'warranty', 'metadata', 'source', 'source_key', 'review_notes'])
                    ->every(fn ($column) => ! array_key_exists($column, $attributes));
        });

        $failed = $this->actingAs($admin)
            ->from(route('admin.catalog.index'))
            ->patch(route('admin.catalog.offers.update', $offer), [
                'decision' => 'changes_requested',
                'reason' => 'PRIVATE CATALOG REVIEW NOTE',
                'current_password' => 'wrong-password',
            ])
            ->assertRedirect(route('admin.catalog.index'))
            ->assertSessionHasErrors('current_password');
        $oldInput = $failed->getSession()->getOldInput();
        $this->assertArrayNotHasKey('current_password', $oldInput);
        $this->assertArrayNotHasKey('reason', $oldInput);
        $this->assertSame(ProductOfferStatus::PendingReview, $offer->fresh()->status);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'catalog.offer_changes_requested']);

        $this->assertThrows(
            fn () => app(CatalogModerationService::class)->reviewOffer(
                $offer,
                ProductOfferStatus::Active,
                $customer,
                null,
            ),
            HttpException::class,
        );
        $this->assertSame(ProductOfferStatus::PendingReview, $offer->fresh()->status);
    }

    public function test_merchant_moderator_cannot_review_own_product_or_offer(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $merchant = $this->merchant(MerchantVerificationStatus::Verified);
        $merchant->user->assignRole('admin');
        $category = Category::query()->where('path', 'electronics/phones')->firstOrFail();
        $payload = array_merge($this->offerPayload(), [
            'name' => 'Self Review Product',
            'category_id' => $category->id,
            'description' => 'Must be reviewed independently',
            'image' => UploadedFile::fake()->createWithContent(
                'self-review.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nAAAAABJRU5ErkJggg=='),
            ),
        ]);

        $this->actingAs($merchant->user)->post('/merchant/catalog', $payload)
            ->assertRedirect(route('merchant.catalog.index'));
        $product = Product::query()->where('name', 'Self Review Product')->firstOrFail();
        $offer = $product->offers()->firstOrFail();

        $this->actingAs($merchant->user)->get(route('admin.catalog.index'))
            ->assertOk()
            ->assertViewHas('products', fn ($products) => ! $products->contains('id', $product->id))
            ->assertViewHas('offers', fn ($offers) => ! $offers->contains('id', $offer->id));
        $this->actingAs($merchant->user)->patch(route('admin.catalog.products.update', $product), [
            'decision' => 'active',
            'current_password' => 'password',
        ])->assertForbidden();
        $this->actingAs($merchant->user)->patch(route('admin.catalog.offers.update', $offer), [
            'decision' => 'active',
            'current_password' => 'password',
        ])->assertForbidden();

        $service = app(CatalogModerationService::class);
        $this->assertThrows(
            fn () => $service->reviewProduct($product, ProductStatus::Active, $merchant->user, null),
            HttpException::class,
        );
        $this->assertThrows(
            fn () => $service->reviewOffer($offer, ProductOfferStatus::Active, $merchant->user, null),
            HttpException::class,
        );
        $this->assertSame(ProductStatus::PendingReview, $product->fresh()->status);
        $this->assertSame(ProductOfferStatus::PendingReview, $offer->fresh()->status);
    }

    public function test_active_offer_operational_fields_update_immediately_and_reprice_server_side(): void
    {
        [$merchant, $offer] = $this->activeMerchantOffer();
        $originalVersion = $offer->lock_version;
        $payload = $this->updatePayload($offer, [
            'price' => 64.50,
            'stock' => 9,
            'variant_sizes' => '41, 43',
            'variant_color' => 'Blue',
        ]);

        $this->actingAs($merchant->user)
            ->patch(route('merchant.catalog.update', $offer), $payload)
            ->assertRedirect(route('merchant.catalog.index'));

        $offer->refresh();
        $this->assertSame(ProductOfferStatus::Active, $offer->status);
        $this->assertSame('64.50', $offer->price);
        $this->assertSame(9, $offer->stock);
        $this->assertSame($originalVersion + 1, $offer->lock_version);
        $activeSizes = $offer->variants()->active()->get()
            ->pluck('attributes')->pluck('size')->sort()->values()->all();
        $this->assertSame(['41', '43'], $activeSizes);

        $resolved = app(CatalogItemResolver::class)->resolve(null, $offer->product->slug, $offer->id);
        $this->assertSame(64.5, $resolved['price']);
        $this->assertTrue(AuditLog::query()->where('action', 'catalog.offer_operational_updated')->exists());
    }

    public function test_changes_requested_offer_is_resubmitted_after_merchant_update(): void
    {
        $merchant = $this->merchant(MerchantVerificationStatus::Verified);
        $admin = $this->admin();
        $product = $this->activeProduct();
        $this->actingAs($merchant->user)->post('/merchant/catalog', $this->offerPayload($product->id));
        $offer = ProductOffer::query()->firstOrFail();

        $this->actingAs($admin)->patch(route('admin.catalog.offers.update', $offer), [
            'decision' => 'changes_requested',
            'reason' => 'عدّل السعر والمخزون',
            'current_password' => 'password',
        ]);
        $offer->refresh();
        $this->assertSame(ProductOfferStatus::ChangesRequested, $offer->status);

        $this->actingAs($merchant->user)
            ->patch(route('merchant.catalog.update', $offer), $this->updatePayload($offer, [
                'price' => 69.99,
                'stock' => 7,
            ]))
            ->assertRedirect(route('merchant.catalog.index'));

        $offer->refresh();
        $this->assertSame(ProductOfferStatus::PendingReview, $offer->status);
        $this->assertNull($offer->review_notes);
        $this->assertNull($offer->reviewed_by);
        $this->assertTrue(AuditLog::query()->where('action', 'catalog.offer_resubmitted')->exists());
    }

    public function test_stale_offer_form_cannot_overwrite_a_newer_update(): void
    {
        [$merchant, $offer] = $this->activeMerchantOffer();
        $stalePayload = $this->updatePayload($offer, ['price' => 55.00]);

        $this->actingAs($merchant->user)
            ->patch(route('merchant.catalog.update', $offer), $stalePayload)
            ->assertRedirect(route('merchant.catalog.index'));
        $this->assertSame('55.00', $offer->fresh()->price);

        $stalePayload['price'] = 10.00;
        $this->actingAs($merchant->user)
            ->from(route('merchant.catalog.edit', $offer))
            ->patch(route('merchant.catalog.update', $offer), $stalePayload)
            ->assertRedirect(route('merchant.catalog.edit', $offer))
            ->assertSessionHasErrors('lock_version');

        $this->assertSame('55.00', $offer->fresh()->price);
    }

    public function test_merchant_can_resume_only_an_offer_they_paused_themselves(): void
    {
        [$merchant, $offer, $admin] = $this->activeMerchantOffer();

        $this->actingAs($merchant->user)
            ->post(route('merchant.catalog.pause', $offer), ['lock_version' => $offer->lock_version])
            ->assertRedirect();
        $offer->refresh();
        $this->assertSame(ProductOfferStatus::Paused, $offer->status);
        $this->assertSame($merchant->user_id, $offer->paused_by);
        $this->assertSame('merchant_requested', $offer->pause_reason);

        $this->actingAs($merchant->user)
            ->post(route('merchant.catalog.resume', $offer), ['lock_version' => $offer->lock_version])
            ->assertRedirect();
        $offer->refresh();
        $this->assertSame(ProductOfferStatus::Active, $offer->status);

        $this->actingAs($admin)->patch(route('admin.catalog.offers.update', $offer), [
            'decision' => 'paused',
            'reason' => 'إيقاف إداري',
            'current_password' => 'password',
        ]);
        $offer->refresh();
        $this->assertSame('admin_moderation', $offer->pause_reason);

        $this->actingAs($merchant->user)
            ->post(route('merchant.catalog.resume', $offer), ['lock_version' => $offer->lock_version])
            ->assertForbidden();
        $this->assertSame(ProductOfferStatus::Paused, $offer->fresh()->status);
    }

    private function offerPayload(?int $productId = null): array
    {
        return [
            'product_mode' => $productId ? 'existing' : 'new',
            'product_id' => $productId,
            'price' => 79.99,
            'compare_at_price' => 100,
            'stock' => 5,
            'location_id' => Location::query()->firstOrFail()->id,
            'preparation_time_days' => 2,
            'warranty' => 'ضمان شهر',
            'variant_sizes' => '40, 41, 42',
            'variant_color' => 'Black',
        ];
    }

    private function updatePayload(ProductOffer $offer, array $overrides = []): array
    {
        return array_merge([
            'lock_version' => $offer->lock_version,
            'price' => (float) $offer->price,
            'compare_at_price' => $offer->compare_at_price !== null ? (float) $offer->compare_at_price : null,
            'stock' => $offer->stock,
            'location_id' => $offer->location_id,
            'preparation_time_days' => $offer->preparation_time_days,
            'warranty' => $offer->warranty,
            'variant_sizes' => '40, 41, 42',
            'variant_color' => 'Black',
        ], $overrides);
    }

    private function activeMerchantOffer(): array
    {
        $merchant = $this->merchant(MerchantVerificationStatus::Verified);
        $admin = $this->admin();
        $product = $this->activeProduct();
        $this->actingAs($merchant->user)->post('/merchant/catalog', $this->offerPayload($product->id));
        $offer = ProductOffer::query()->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.catalog.offers.update', $offer), [
            'decision' => 'active',
            'current_password' => 'password',
        ]);

        return [$merchant, $offer->fresh(['product']), $admin];
    }

    private function activeProduct(): Product
    {
        return Product::query()->create([
            'name' => 'Canonical Existing Product',
            'slug' => 'canonical-existing-product',
            'price' => 100,
            'sale_price' => null,
            'status' => ProductStatus::Active,
            'in_stock' => true,
        ]);
    }

    private function merchant(MerchantVerificationStatus $status): Merchant
    {
        $user = User::factory()->create();

        return Merchant::query()->create([
            'user_id' => $user->id,
            'location_id' => Location::query()->firstOrFail()->id,
            'legal_name' => 'Test Merchant '.$user->id,
            'identity_number' => 'ID-'.$user->id.'-'.fake()->unique()->numerify('#####'),
            'phone' => '059900000'.$user->id,
            'date_of_birth' => now()->subYears(30),
            'address' => 'Test address',
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
}

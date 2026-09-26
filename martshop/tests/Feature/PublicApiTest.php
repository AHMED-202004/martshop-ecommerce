<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MarketplaceSetting;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Services\CatalogQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogue_uses_only_public_fields_and_the_best_sellable_offer(): void
    {
        $product = $this->product('public-item');
        $product->offers()->create(['price' => 15, 'stock' => 2, 'status' => 'active', 'currency' => 'ILS']);
        $product->offers()->create(['price' => 1, 'stock' => 0, 'status' => 'active']);
        $this->product('hidden-item', 'hidden');
        $this->product('pending-item', 'pending_review');
        $expired = $this->product('expired-item');
        $expired->offers()->update(['expires_at' => now()->subMinute()]);
        $paused = $this->product('paused-item');
        $paused->offers()->update(['status' => 'paused']);

        $response = $this->get('/api/v1/products')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.price', '15.00')
            ->assertJsonPath('data.0.currency', 'ILS')
            ->assertJsonStructure(['data', 'links', 'meta']);
        $this->assertSame([
            'id', 'slug', 'name', 'image_url', 'brand', 'color', 'sizes', 'offer_id', 'price', 'currency',
        ], array_keys($response->json('data.0')));
        $response->assertDontSee('internal-only-secret');
        $this->get('/api/v1/products/public-item')->assertOk()->assertJsonPath('data.id', $product->id);
        foreach (['hidden-item', 'pending-item', 'expired-item', 'paused-item', 'missing'] as $slug) {
            $this->get('/api/v1/products/'.$slug)->assertNotFound()->assertHeader('Content-Type', 'application/json');
        }
    }

    public function test_filters_pagination_and_active_categories(): void
    {
        $category = Category::query()->create(['name' => 'Public', 'slug' => 'public', 'path' => 'public', 'status' => 'active']);
        Category::query()->create(['name' => 'Hidden', 'slug' => 'hidden', 'path' => 'hidden', 'status' => 'hidden']);
        $product = $this->product('filtered');
        $product->categories()->attach($category);
        $this->product('other');
        $this->get('/api/v1/categories')->assertOk()->assertJsonCount(1, 'data');
        $this->get('/api/v1/products?category=public&q=filtered')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $product->id);
        $this->get('/api/v1/products?per_page=1')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 2);
        $this->get('/api/v1/products?per_page=51')->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->get('/api/v1/products?page=0')->assertUnprocessable()->assertJsonValidationErrors('page');
        $this->get('/api/v1/products?category=hidden')->assertNotFound();
    }

    public function test_hidden_category_ancestors_hide_active_descendants_from_api_navigation_and_filters(): void
    {
        $hidden = Category::query()->create([
            'name' => 'Hidden branch', 'slug' => 'private_branch',
            'path' => 'private_branch', 'status' => 'hidden',
        ]);
        $descendant = Category::query()->create([
            'parent_id' => $hidden->id, 'name' => 'Active descendant', 'slug' => 'leaf',
            'path' => 'private_branch/leaf', 'depth' => 1, 'status' => 'active',
        ]);
        Category::query()->create([
            'name' => 'Similar public branch', 'slug' => 'privateXbranch',
            'path' => 'privateXbranch', 'status' => 'active',
        ]);
        $product = $this->product('hidden-category-product');
        $product->categories()->attach($descendant);

        $categories = $this->get('/api/v1/categories')->assertOk();
        $paths = collect($categories->json('data'))->pluck('path');
        $this->assertNotContains('private_branch', $paths);
        $this->assertNotContains('private_branch/leaf', $paths);
        $this->assertContains('privateXbranch', $paths);

        $this->get('/api/v1/products?'.http_build_query(['category' => 'private_branch/leaf']))
            ->assertNotFound();
        $this->get('/api/v1/products')
            ->assertOk()
            ->assertJsonMissing(['slug' => 'hidden-category-product']);
    }

    public function test_settings_are_allowlisted_and_api_does_not_accept_writes(): void
    {
        MarketplaceSetting::query()->create(['key' => 'private.api_secret', 'value' => 'never-expose']);
        $this->get('/api/v1/settings')->assertOk()->assertJsonPath('data.name', 'Mart.ps')
            ->assertJsonPath('data.orders_enabled', true)
            ->assertJsonPath('data.merchant_registration_enabled', true)
            ->assertJsonPath('data.chat_enabled', true)
            ->assertJsonPath('data.whatsapp', '')
            ->assertJsonPath('data.delivery_text', 'رسوم التوصيل الأساسية ₪20.00، ومجانية للطلبات من ₪250.00. تُحسب الرسوم النهائية عند إتمام الطلب.')
            ->assertDontSee('never-expose')
            ->assertJsonMissingPath('data.withdrawals');

        MarketplaceSetting::query()->updateOrCreate(
            ['key' => 'site.default_delivery_text'],
            ['value' => '<b>نص توصيل مخصص</b>', 'type' => 'string', 'group' => 'delivery'],
        );
        $this->get('/api/v1/settings')->assertOk()
            ->assertJsonPath('data.delivery_text', '<b>نص توصيل مخصص</b>')
            ->assertDontSee('never-expose');

        $this->post('/api/v1/products', ['name' => 'injected'])->assertStatus(405)
            ->assertHeader('Content-Type', 'application/json');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_cross_origin_requests_are_not_permitted_implicitly(): void
    {
        $this->withHeader('Origin', 'https://attacker.example')
            ->getJson('/api/v1/settings')
            ->assertOk()
            ->assertHeaderMissing('Access-Control-Allow-Origin')
            ->assertHeaderMissing('Access-Control-Allow-Credentials');

        $this->withHeaders([
            'Origin' => 'https://attacker.example',
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/v1/products')
            ->assertNoContent()
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_public_api_has_a_rate_limit(): void
    {
        for ($attempt = 0; $attempt < 60; $attempt++) {
            $this->get('/api/v1/settings')->assertOk();
        }
        $this->get('/api/v1/settings')->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_search_treats_wildcards_as_literal_characters(): void
    {
        $product = $this->product('literal-discount');
        $product->update(['name' => 'Discount 10%_special!']);
        $this->product('ordinary-item');
        $this->get('/api/v1/products?'.http_build_query(['q' => '10%_special!']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $product->id);
        $this->get('/api/v1/products?'.http_build_query(['q' => "' OR 1=1 --"]))
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_api_catalogue_query_does_not_hydrate_internal_catalogue_fields(): void
    {
        $product = $this->product('minimized-api-item');
        $product->update(['source_key' => 'private-source', 'metadata' => ['secret' => 'private-metadata']]);
        $offer = $product->offers()->firstOrFail();
        $offer->update(['source_key' => 'private-offer-source', 'metadata' => ['secret' => 'private-offer-metadata']]);
        $offer->variants()->create([
            'sku' => 'PRIVATE-SKU', 'source_key' => 'private-variant-source',
            'attributes' => ['size' => 'L', 'color' => 'Black'], 'status' => 'active',
        ]);
        $offer->variants()->create([
            'sku' => 'SOLD-OUT-SKU', 'source_key' => 'sold-out-variant-source',
            'attributes' => ['size' => 'SOLD-OUT'], 'stock' => 0, 'status' => 'active',
        ]);

        $loaded = app(CatalogQuery::class)->publicApiProducts()->whereKey($product->id)->sole();
        $this->assertEqualsCanonicalizing(
            ['id', 'brand_id', 'name', 'slug', 'price', 'sale_price', 'image'],
            array_keys($loaded->getAttributes()),
        );
        $this->assertEqualsCanonicalizing(
            ['id', 'product_id', 'price', 'compare_at_price', 'currency'],
            array_keys($loaded->offers->firstOrFail()->getAttributes()),
        );
        $this->assertEqualsCanonicalizing(
            ['id', 'product_offer_id', 'attributes'],
            array_keys($loaded->offers->firstOrFail()->variants->sole()->getAttributes()),
        );
        foreach (['private-source', 'private-metadata', 'private-offer-source', 'private-offer-metadata',
            'PRIVATE-SKU', 'private-variant-source'] as $private) {
            $this->assertStringNotContainsString($private, $loaded->toJson());
        }
        $this->assertStringNotContainsString('SOLD-OUT', $loaded->toJson());
    }

    public function test_catalogue_models_hide_internal_metadata_by_default(): void
    {
        $product = $this->product('serialization-item');
        foreach (['metadata', 'review_notes', 'source_key', 'submission_image_disk',
            'submission_image_path', 'submission_image_size', 'submission_image_sha256'] as $key) {
            $this->assertArrayNotHasKey($key, $product->toArray());
        }
        foreach (['metadata', 'review_notes', 'source_key', 'pause_reason'] as $key) {
            $this->assertArrayNotHasKey($key, $product->offers()->firstOrFail()->toArray());
        }
        $category = new Category(['metadata' => ['private' => true]]);
        $this->assertArrayNotHasKey('metadata', $category->toArray());
    }

    private function product(string $slug, string $status = 'active'): Product
    {
        $product = Product::query()->create([
            'name' => $slug, 'slug' => $slug, 'price' => 100, 'status' => $status,
            'metadata' => ['secret' => 'internal-only-secret'], 'review_notes' => 'internal-only-secret',
        ]);
        ProductOffer::query()->create([
            'product_id' => $product->id, 'price' => 20, 'currency' => 'ILS', 'stock' => 5, 'status' => 'active',
        ]);

        return $product;
    }
}

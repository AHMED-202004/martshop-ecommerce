<?php

namespace Tests\Feature;

use App\Models\{Brand, Category, MarketplaceSetting, Merchant, Product, ProductOffer, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicWebCatalogSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_and_unsellable_merchant_product_slugs_return_not_found(): void
    {
        $this->get(route('product.show', 'definitely-not-a-real-product'))->assertNotFound();

        $product = $this->merchantProduct('suspended-private-product', 'suspended');
        $this->get(route('product.show', $product->slug))->assertNotFound();
        $this->get('/api/v1/products')->assertOk()->assertDontSee($product->slug);
    }

    public function test_product_routes_accept_supported_slugs_and_reject_unbounded_or_malformed_paths(): void
    {
        $product = Product::query()->create([
            'name' => 'Supported slug', 'slug' => 'Supported-Grey_1',
            'price' => 10, 'status' => 'active', 'source' => 'legacy', 'in_stock' => true,
        ]);
        ProductOffer::query()->create([
            'product_id' => $product->id, 'price' => 10, 'currency' => 'ILS',
            'stock' => 1, 'status' => 'active',
        ]);

        $this->get('/p/Supported-Grey_1')->assertOk();
        $this->getJson('/api/v1/products/Supported-Grey_1')->assertOk();
        $this->get('/p/'.str_repeat('a', 201))->assertNotFound();
        $this->getJson('/api/v1/products/'.str_repeat('a', 201))->assertNotFound();
        $this->get('/p/invalid.slug')->assertNotFound();
        $this->getJson('/api/v1/products/invalid.slug')->assertNotFound();
    }

    public function test_product_page_uses_escaped_operational_delivery_settings_and_current_payment_flow(): void
    {
        $deliveryText = '<script>alert("delivery")</script> التوصيل حسب المنطقة';
        MarketplaceSetting::query()->create([
            'key' => 'site.default_delivery_text', 'value' => $deliveryText,
            'type' => 'string', 'group' => 'delivery',
        ]);
        $product = Product::query()->create([
            'name' => 'Operational details item', 'slug' => 'operational-details-item',
            'price' => 10, 'status' => 'active', 'source' => 'legacy', 'in_stock' => true,
        ]);
        ProductOffer::query()->create([
            'product_id' => $product->id, 'price' => 10, 'currency' => 'ILS',
            'stock' => 1, 'status' => 'active',
        ]);

        $this->get(route('product.show', $product->slug))
            ->assertOk()
            ->assertSee($deliveryText)
            ->assertDontSee($deliveryText, false)
            ->assertSee('تحويل يدوي عبر وسائل الدفع المفعلة')
            ->assertDontSee('الدفع عند الاستلام')
            ->assertDontSee('يمكن التبديل خلال 24 ساعة')
            ->assertDontSee('مناطق 48');

        $this->get(route('policies'))
            ->assertOk()
            ->assertSee($deliveryText)
            ->assertDontSee($deliveryText, false)
            ->assertSee('التحويل اليدوي')
            ->assertSee(route('orders.history'), false)
            ->assertDontSee('martprime')
            ->assertDontSee('مجاني فوق ₪1000')
            ->assertDontSee('عند الاستلام')
            ->assertDontSee('البطاقات الائتمانية')
            ->assertDontSee('طلب الإرجاع عبر خدمة الشات')
            ->assertDontSee('20 شيكل في الضفة')
            ->assertDontSee('5 - 7 أيام');

        $faq = $this->get(route('faq'))
            ->assertOk()
            ->assertSee('يمكنك فتح نزاع من تاريخ الطلبات بعد التسليم')
            ->assertDontSee('خلال 24 ساعة')
            ->assertDontSee('3–7 أيام');
        $this->assertStringNotContainsString('CART_COUNT_URL', $faq->getContent());
        $this->assertStringNotContainsString('hpCartBadge', $faq->getContent());
    }

    public function test_public_listing_pages_exclude_unverified_merchant_offers(): void
    {
        $brand = Brand::query()->create(['name' => 'Protected Brand', 'slug' => 'protected-brand']);
        $hidden = $this->merchantProduct('hidden-merchant-listing', 'pending_review', $brand);
        $hidden->update(['is_super_deal' => true, 'is_new' => true]);
        $visible = Product::query()->create([
            'brand_id' => $brand->id, 'name' => 'Visible legacy listing', 'slug' => 'visible-legacy-listing',
            'price' => 50, 'status' => 'active', 'source' => 'legacy', 'is_super_deal' => true, 'is_new' => true,
        ]);

        foreach ([route('brands.show', $brand), route('deals.index'), route('new.index')] as $url) {
            $this->get($url)->assertOk()->assertSee($visible->name)->assertDontSee($hidden->name);
        }
    }

    public function test_search_and_cart_share_the_publication_rule_and_do_not_fall_back_over_database_slugs(): void
    {
        $hidden = $this->merchantProduct('hidden-search-listing', 'pending_review');

        $search = $this->get(route('search', ['q' => 'hidden-search-listing']))
            ->assertOk();
        $this->assertTrue($search->viewData('products')->getCollection()->isEmpty());

        $legacySlug = 'gabrini-pacific-nailpolish-89';
        $collision = $this->merchantProduct($legacySlug, 'pending_review');
        $this->assertSame($legacySlug, $collision->slug);

        $this->from(route('cart.index'))->post(route('cart.add'), ['slug' => $legacySlug])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHasErrors('cart');
        $this->assertEmpty(session('cart.items', []));
    }

    public function test_out_of_stock_platform_products_are_not_published_or_addable(): void
    {
        $product = Product::query()->create([
            'name' => 'Unavailable platform item', 'slug' => 'unavailable-platform-item',
            'price' => 25, 'status' => 'active', 'source' => 'legacy', 'in_stock' => false,
            'is_super_deal' => true, 'is_new' => true,
        ]);

        $this->get(route('product.show', $product->slug))->assertNotFound();
        $search = $this->get(route('search', ['q' => 'Unavailable platform item']))->assertOk();
        $this->assertTrue($search->viewData('products')->getCollection()->isEmpty());

        foreach ([route('deals.index'), route('new.index')] as $url) {
            $this->get($url)->assertOk()->assertDontSee($product->name);
        }

        $this->from(route('cart.index'))->post(route('cart.add'), ['id' => $product->id])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHasErrors('cart');
        $this->assertEmpty(session('cart.items', []));
    }

    public function test_public_pages_expose_only_available_product_variants(): void
    {
        $product = Product::query()->create([
            'name' => 'Variant availability item', 'slug' => 'variant-availability-item',
            'price' => 30, 'status' => 'active', 'source' => 'legacy', 'in_stock' => true,
            'is_super_deal' => true,
        ]);
        $product->variants()->create([
            'size_type' => 'alpha', 'size_value' => 'S', 'color' => 'أسود', 'in_stock' => true,
        ]);
        $product->variants()->create([
            'size_type' => 'alpha', 'size_value' => 'XL', 'color' => 'أبيض', 'in_stock' => false,
        ]);

        $this->get(route('product.show', $product->slug))
            ->assertOk()
            ->assertSee('data-size="S"', false)
            ->assertDontSee('data-size="XL"', false);
        $this->get(route('deals.index', ['alpha' => ['XL']]))
            ->assertOk()
            ->assertDontSee($product->name);
    }

    public function test_offer_with_only_unavailable_variants_is_not_public_or_addable(): void
    {
        $product = $this->merchantProduct('all-offer-variants-unavailable', 'verified');
        $product->update(['is_super_deal' => true, 'is_new' => true]);
        $offer = $product->offers()->firstOrFail();
        $offer->variants()->create([
            'attributes' => ['size' => 'L'], 'stock' => 0,
            'status' => 'active', 'source_key' => 'sold-out-only',
        ]);
        $offer->variants()->create([
            'attributes' => ['size' => 'XL'], 'stock' => 3,
            'status' => 'paused', 'source_key' => 'paused-only',
        ]);

        $this->assertFalse($offer->fresh()->newQuery()->sellable()->whereKey($offer)->exists());
        $this->get(route('product.show', $product->slug))->assertNotFound();
        $this->getJson('/api/v1/products')->assertOk()->assertDontSee($product->slug);
        $this->get(route('deals.index'))->assertOk()->assertDontSee($product->name);
        $this->get(route('new.index'))->assertOk()->assertDontSee($product->name);
        $this->postJson(route('cart.add'), [
            'id' => $product->id, 'offer_id' => $offer->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('cart');
        $this->assertEmpty(session('cart.items', []));
    }

    public function test_cart_keeps_exact_offer_variants_separate_and_uses_canonical_variant_data(): void
    {
        $product = $this->merchantProduct('exact-offer-variant-item', 'verified');
        $offer = $product->offers()->firstOrFail();
        $first = $offer->variants()->create([
            'attributes' => ['size' => 'L', 'color' => 'Red'],
            'price' => 80, 'stock' => 2, 'status' => 'active', 'source_key' => 'red-l',
        ]);
        $second = $offer->variants()->create([
            'attributes' => ['size' => 'L', 'color' => 'Blue'],
            'price' => 70, 'stock' => 2, 'status' => 'active', 'source_key' => 'blue-l',
        ]);
        $offer->variants()->create([
            'attributes' => ['size' => 'L', 'color' => 'Blue'],
            'price' => 72, 'stock' => 2, 'status' => 'active', 'source_key' => 'blue-l-duplicate',
        ]);

        $this->postJson(route('cart.add'), [
            'id' => $product->id,
            'offer_id' => $offer->id,
            'options' => ['size' => 'L', 'color' => 'Blue'],
        ])->assertUnprocessable()->assertJsonValidationErrors('offer_variant_id');
        $this->assertEmpty(session('cart.items', []));

        $this->get(route('product.show', $product->slug))
            ->assertOk()
            ->assertSee('name="offer_variant_id"', false)
            ->assertSee('data-variant-id="'.$first->id.'"', false)
            ->assertSee('data-variant-id="'.$second->id.'"', false);

        $this->post(route('cart.add'), [
            'id' => $product->id,
            'offer_id' => $offer->id,
            'offer_variant_id' => $second->id,
            'options' => ['size' => 'forged', 'color' => 'forged'],
        ])->assertRedirect(route('cart.index'));

        $row = collect(session('cart.items'))->sole();
        $this->assertSame($second->id, $row['offer_variant_id']);
        $this->assertSame(['size' => 'L', 'color' => 'Blue'], $row['options']);
        $this->assertSame(70.0, $row['price']);

        $this->post(route('cart.add'), [
            'id' => $product->id,
            'offer_id' => $offer->id,
            'offer_variant_id' => $first->id,
        ])->assertRedirect(route('cart.index'));
        $this->assertCount(2, session('cart.items'));
    }

    public function test_category_quick_view_uses_only_available_exact_offer_variants(): void
    {
        $category = Category::query()->create([
            'name' => 'Quick view category', 'slug' => 'quick-view-category',
            'path' => 'quick-view-category', 'status' => 'active',
        ]);
        $product = $this->merchantProduct('quick-view-exact-variant', 'verified');
        $product->update(['name' => 'Quick </div><script>alert("x")</script>']);
        $product->categories()->attach($category);
        $offer = $product->offers()->firstOrFail();
        $available = $offer->variants()->create([
            'sku' => 'PRIVATE-AVAILABLE-SKU',
            'attributes' => ['size' => '42', 'color' => 'Blue'],
            'price' => 75, 'stock' => 2, 'status' => 'active', 'source_key' => 'private-source',
        ]);
        $soldOut = $offer->variants()->create([
            'attributes' => ['size' => '43', 'color' => 'Red'],
            'price' => 70, 'stock' => 0, 'status' => 'active', 'source_key' => 'sold-out-source',
        ]);

        $response = $this->get('/c/quick-view-category')
            ->assertOk()
            ->assertSee('name="offer_variant_id"', false)
            ->assertSee('name="options[color]"', false)
            ->assertSee('\u003Cscript\u003E', false)
            ->assertDontSee('<script>alert', false)
            ->assertDontSee('PRIVATE-AVAILABLE-SKU')
            ->assertDontSee('private-source')
            ->assertDontSee('sold-out-source');

        $presented = $response->viewData('products')->sole();
        $this->assertSame([[
            'id' => $available->id,
            'attributes' => ['size' => '42', 'color' => 'Blue'],
            'price' => 75.0,
        ]], $presented['offer_variants']);
        $this->assertNotSame($soldOut->id, $presented['offer_variants'][0]['id']);
        $this->assertSame(1, substr_count($response->getContent(), 'assets/category.js'));
        $this->assertSame(1, substr_count(
            file_get_contents(public_path('assets/category.js')),
            "\$\$('.js-quick').forEach",
        ));
    }

    public function test_product_assigned_only_beneath_a_hidden_category_is_not_public(): void
    {
        $hidden = \App\Models\Category::query()->create([
            'name' => 'Hidden catalogue', 'slug' => 'hidden-catalogue',
            'path' => 'hidden-catalogue', 'status' => 'hidden',
        ]);
        $child = \App\Models\Category::query()->create([
            'parent_id' => $hidden->id, 'name' => 'Active-looking child', 'slug' => 'child',
            'path' => 'hidden-catalogue/child', 'depth' => 1, 'status' => 'active',
        ]);
        $product = Product::query()->create([
            'name' => 'Hidden category web product', 'slug' => 'hidden-category-web-product',
            'price' => 20, 'status' => 'active', 'source' => 'legacy', 'in_stock' => true,
        ]);
        $product->categories()->attach($child);

        $this->get(route('product.show', $product->slug))->assertNotFound();
        $this->get(route('search', ['q' => 'Hidden category web product']))
            ->assertOk()
            ->assertViewHas('products', fn ($products) => $products->isEmpty());
    }

    public function test_public_listing_cards_use_the_best_sellable_offer_price_and_variants(): void
    {
        $brand = Brand::query()->create(['name' => 'Offer Price Brand', 'slug' => 'offer-price-brand']);
        $product = $this->merchantProduct('offer-priced-listing', 'verified', $brand);
        $product->update([
            'price' => 100, 'sale_price' => 90, 'is_super_deal' => true, 'is_new' => true,
        ]);
        $product->variants()->create([
            'size_type' => 'alpha', 'size_value' => 'PRODUCT-XL', 'in_stock' => true,
        ]);
        $offer = $product->offers()->firstOrFail();
        $offer->update(['price' => 35, 'compare_at_price' => 50]);
        $offer->variants()->create([
            'attributes' => ['size' => 'OFFER-S'], 'status' => 'active', 'source_key' => 'offer-s',
        ]);
        $bestOffer = ProductOffer::query()->create([
            'product_id' => $product->id, 'price' => 20, 'compare_at_price' => 30,
            'currency' => 'ILS', 'stock' => 2, 'status' => 'active',
        ]);
        $bestOffer->variants()->create([
            'attributes' => ['size' => 'OFFER-XS'], 'status' => 'active', 'source_key' => 'offer-xs',
        ]);
        $bestOffer->variants()->create([
            'attributes' => ['size' => 'SOLD-OUT-SIZE'], 'stock' => 0,
            'status' => 'active', 'source_key' => 'sold-out-size',
        ]);

        foreach ([route('brands.show', $brand), route('deals.index'), route('new.index')] as $url) {
            $response = $this->get($url)
                ->assertOk()
                ->assertSee('₪20.00')
                ->assertSee('₪30')
                ->assertSee('OFFER-XS')
                ->assertDontSee('SOLD-OUT-SIZE')
                ->assertDontSee('OFFER-S')
                ->assertDontSee('PRODUCT-XL')
                ->assertDontSee('₪90.00');
            if ($url !== route('brands.show', $brand)) {
                $response->assertSee('action="'.route('search').'"', false)
                    ->assertSee('maxlength="100"', false);
                $this->assertListingCardStructure($response->getContent());
            } else {
                $response->assertSee('data-qv-key="'.$product->slug.'"', false)
                    ->assertSee('id="martQuickViewProductsData"', false)
                    ->assertDontSee('MART_QUICK_VIEW_PRODUCTS', false)
                    ->assertDontSee("data-qv='", false)
                    ->assertDontSee('data-add-to-cart', false)
                    ->assertDontSee('grid-template-columns:repeat(4,1fr)', false);
            }
        }
    }

    public function test_deals_and_new_product_navigation_omit_hidden_database_roots(): void
    {
        Category::query()->create([
            'name' => 'Visible navigation root', 'slug' => 'visible-navigation-root',
            'path' => 'visible-navigation-root', 'status' => 'active',
        ]);
        Category::query()->create([
            'name' => 'Hidden navigation root', 'slug' => 'hidden-navigation-root',
            'path' => 'hidden-navigation-root', 'status' => 'hidden',
        ]);

        foreach ([route('deals.index'), route('new.index')] as $url) {
            $response = $this->get($url)
                ->assertOk()
                ->assertSee('/c/visible-navigation-root', false)
                ->assertDontSee('/c/hidden-navigation-root', false)
                ->assertDontSee('/c/shoes', false);
            if ($url === route('new.index')) {
                $response->assertSee('aria-controls="catsDrawer" aria-expanded="false"', false)
                    ->assertSee('aria-hidden="true"', false)
                    ->assertSee('aria-label="إغلاق التصنيفات"', false)
                    ->assertDontSee('padding:2px px', false);
            }
        }
    }

    public function test_deal_filters_are_bounded_allowlisted_and_search_wildcards_are_literal(): void
    {
        Product::query()->create([
            'name' => 'Deal 10%_special!', 'slug' => 'deal-literal-special', 'price' => 40,
            'status' => 'active', 'source' => 'legacy', 'is_super_deal' => true,
        ]);
        Product::query()->create([
            'name' => 'Ordinary unrelated deal', 'slug' => 'ordinary-unrelated-deal', 'price' => 45,
            'status' => 'active', 'source' => 'legacy', 'is_super_deal' => true,
        ]);

        $this->get(route('deals.index', ['q' => '10%_special!']))
            ->assertOk()
            ->assertSee('Deal 10%_special!')
            ->assertDontSee('Ordinary unrelated deal')
            ->assertDontSee("document.querySelectorAll('#filtersForm input[type=checkbox]')", false);
        $this->get(route('deals.index', ['color' => ['not-allowlisted']]))
            ->assertSessionHasErrors('color.0');
        $this->get(route('deals.index', ['brand' => array_fill(0, 51, 1)]))
            ->assertSessionHasErrors('brand');
        $this->get(route('deals.index', ['page' => 0]))->assertSessionHasErrors('page');
    }

    public function test_legacy_women_shoes_url_redirects_to_the_working_category_route(): void
    {
        $this->get(route('shoes.women'))->assertRedirect('/c/shoes/women')->assertStatus(301);
    }

    private function merchantProduct(string $slug, string $verification, ?Brand $brand = null): Product
    {
        $user = User::factory()->create();
        $merchant = Merchant::query()->create([
            'user_id' => $user->id, 'legal_name' => 'Private merchant '.$slug,
            'identity_number' => 'CAT-'.$user->id.'-'.$slug, 'phone' => '0599000000',
            'date_of_birth' => '1990-01-01', 'address' => 'Private address',
            'business_type' => 'Retail', 'verification_status' => $verification,
        ]);
        $product = Product::query()->create([
            'brand_id' => $brand?->id, 'created_by_merchant_id' => $merchant->id,
            'name' => $slug, 'slug' => $slug, 'price' => 100,
            'status' => 'active', 'source' => 'merchant_submission',
        ]);
        ProductOffer::query()->create([
            'product_id' => $product->id, 'merchant_id' => $merchant->id,
            'price' => 100, 'currency' => 'ILS', 'stock' => 5, 'status' => 'active',
        ]);

        return $product;
    }

    private function assertListingCardStructure(string $html): void
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $cardClass = "contains(concat(' ', normalize-space(@class), ' '), ' product-card ')";
        $imageClass = "contains(concat(' ', normalize-space(@class), ' '), ' img-wrap ')";
        $bodyClass = "contains(concat(' ', normalize-space(@class), ' '), ' p-body ')";

        $this->assertGreaterThan(0, $xpath->query("//article[$cardClass]/div[$bodyClass]")->length);
        $this->assertSame(0, $xpath->query("//article[$cardClass]/div[$imageClass]/div[$bodyClass]")->length);
    }
}

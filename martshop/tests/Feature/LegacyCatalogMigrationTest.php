<?php

namespace Tests\Feature;

use App\Http\Controllers\CategoryController;
use App\Models\Category;
use App\Models\OfferVariant;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\User;
use Database\Seeders\CategoryTreeSeeder;
use Database\Seeders\LegacyCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LegacyCatalogMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogue_import_is_complete_and_idempotent(): void
    {
        $this->seed(CategoryTreeSeeder::class);
        $catalogue = app(CategoryController::class)->legacyProductCatalogue();
        $rows = $this->catalogueRows($catalogue);
        $expectedProducts = $rows->pluck('slug')->unique()->count();
        $expectedListings = $rows->unique(fn (array $row) => $row['slug'].'|'.$row['path'])->count();
        $expectedVariants = $rows
            ->groupBy('slug')
            ->sum(fn (Collection $group) => $group
                ->flatMap(fn (array $row) => $row['sizes'] ?? [])
                ->map(fn ($size) => (string) $size)
                ->unique()
                ->count());

        $this->seed(LegacyCatalogSeeder::class);

        $this->assertSame($expectedProducts, Product::query()->count());
        $this->assertSame($expectedProducts, ProductOffer::query()->count());
        $this->assertSame($expectedListings, DB::table('category_product')->count());
        $this->assertSame($expectedVariants, OfferVariant::query()->count());
        $this->assertSame(
            $rows->pluck('slug')->unique()->sort()->values()->all(),
            Product::query()->pluck('slug')->sort()->values()->all()
        );

        $counts = [
            Product::query()->count(),
            ProductOffer::query()->count(),
            OfferVariant::query()->count(),
            DB::table('category_product')->count(),
        ];
        $this->seed(LegacyCatalogSeeder::class);

        $this->assertSame($counts, [
            Product::query()->count(),
            ProductOffer::query()->count(),
            OfferVariant::query()->count(),
            DB::table('category_product')->count(),
        ]);
    }

    public function test_existing_canonical_product_data_is_preserved_during_import(): void
    {
        $this->seed(CategoryTreeSeeder::class);
        $existing = Product::query()->create([
            'name' => 'Curated product name',
            'slug' => 'adidas-ultimashow-2-grey',
            'price' => 999.99,
            'sale_price' => null,
            'status' => 'active',
            'in_stock' => true,
        ]);

        $this->seed(LegacyCatalogSeeder::class);

        $existing->refresh();
        $this->assertSame('Curated product name', $existing->name);
        $this->assertSame('999.99', $existing->price);
        $this->assertNull($existing->source);
        $this->assertNotNull($existing->category_id);
        $this->assertTrue($existing->categories()->where('path', 'shoes/men/sport')->exists());

        $offer = $existing->offers()->where('source', 'legacy_demo')->firstOrFail();
        $this->assertSame('249.99', $offer->price);
        $this->assertSame('280.00', $offer->compare_at_price);
    }

    public function test_database_catalogue_drives_category_product_cart_and_order_snapshot(): void
    {
        $this->seed(CategoryTreeSeeder::class);
        $this->seed(LegacyCatalogSeeder::class);

        $product = Product::query()->where('slug', 'adidas-ultimashow-2-grey')->firstOrFail();
        $offer = $product->offers()->firstOrFail();
        $product->update(['name' => 'Canonical DB Product']);

        $this->get('/c/shoes/men/sport')
            ->assertOk()
            ->assertSee('Canonical DB Product');
        $this->get('/p/adidas-ultimashow-2-grey')
            ->assertOk()
            ->assertSee('Canonical DB Product')
            ->assertSee('249.99');

        $this->post('/cart/add', [
            'id' => $product->id,
            'slug' => $product->slug,
            'offer_id' => $offer->id,
            'price' => 0.01,
            'options' => ['size' => '42'],
        ])->assertRedirect('/cart');

        $cartItem = collect(session('cart.items'))->first();
        $this->assertSame(249.99, $cartItem['price']);
        $this->assertSame($offer->id, $cartItem['offer_id']);

        $user = User::factory()->create();
        $this->actingAs($user)
            ->post('/checkout/confirm', ['payment_method' => 'manual_transfer'])
            ->assertRedirect();

        $orderItem = OrderItem::query()->firstOrFail();
        $this->assertSame($product->id, $orderItem->product_id);
        $this->assertSame($offer->id, $orderItem->product_offer_id);
        $this->assertSame('249.99', $orderItem->price);
        $this->assertSame('42', $orderItem->variant_snapshot['size']);
        $this->assertSame(249.99, $orderItem->offer_snapshot['price']);
    }

    private function catalogueRows(array $catalogue): Collection
    {
        return collect($catalogue)->flatMap(fn (array $products, string $path) => collect($products)
            ->map(fn (array $product) => array_merge($product, ['path' => $path])))
            ->values();
    }
}

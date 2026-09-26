<?php

namespace Tests\Feature;

use App\Http\Controllers\CategoryController;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Services\CatalogQuery;
use Database\Seeders\CategoryTreeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryDatabaseMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_quick_view_has_accessible_modal_controls_and_no_emoji_action(): void
    {
        $view = file_get_contents(resource_path('views/category/show.blade.php'));
        $script = file_get_contents(public_path('assets/category.js'));

        $this->assertIsString($view);
        $this->assertIsString($script);
        $this->assertStringContainsString('role="dialog" aria-modal="true" aria-labelledby="qTitle"', $view);
        $this->assertStringContainsString('aria-label="إغلاق العرض السريع"', $view);
        $this->assertStringContainsString("event.key==='Escape'", $script);
        $this->assertStringNotContainsString('⚡', $view);
        $this->assertStringNotContainsString("data-product='", $view);
        $this->assertStringContainsString('data-products="{{ json_encode(', $view);
        $this->assertStringContainsString("asset('assets/category.js')", $view);
        $this->assertStringContainsString("asset('assets/category.css')", $view);
        $this->assertStringNotContainsString('<style', $view);
        $this->assertStringNotContainsString('style="', $view);
        $this->assertStringNotContainsString('Illuminate\\Support\\Js::from', $view);
        $this->assertStringNotContainsString('name="name"', $view);
        $this->assertStringNotContainsString('name="price"', $view);
        $this->assertStringNotContainsString('name="image"', $view);
    }

    public function test_the_legacy_tree_is_imported_without_losing_any_path(): void
    {
        $this->seed(CategoryTreeSeeder::class);

        $expected = $this->flattenPaths(app(CategoryController::class)->legacyTree());
        $actual = Category::query()->orderBy('path')->pluck('path')->all();
        sort($expected);

        $this->assertSame($expected, $actual);
    }

    public function test_the_importer_is_idempotent(): void
    {
        $this->seed(CategoryTreeSeeder::class);
        $firstCount = Category::query()->count();

        $this->seed(CategoryTreeSeeder::class);

        $this->assertSame($firstCount, Category::query()->count());
        $this->assertSame($firstCount, Category::query()->distinct()->count('path'));
    }

    public function test_category_navigation_reads_from_the_database_when_available(): void
    {
        $this->seed(CategoryTreeSeeder::class);
        Category::query()->where('path', 'shoes')->update(['name' => 'أحذية من قاعدة البيانات']);

        $this->get('/categories')
            ->assertOk()
            ->assertSee('أحذية من قاعدة البيانات');

        $this->get('/c/shoes')
            ->assertOk()
            ->assertSee('أحذية من قاعدة البيانات');
    }

    public function test_nails_uses_one_canonical_path_and_keeps_the_legacy_redirect(): void
    {
        $this->seed(CategoryTreeSeeder::class);

        $this->get('/c/beauty/makeup/nails')->assertOk();
        $this->get('/c/beauty/makeup/nailpolish')
            ->assertStatus(301)
            ->assertRedirect('/c/beauty/makeup/nails');
    }

    public function test_hidden_database_category_and_its_descendants_cannot_fall_back_to_legacy_content(): void
    {
        $this->seed(CategoryTreeSeeder::class);
        Category::query()->where('path', 'shoes/men')->update(['status' => 'hidden']);

        $this->get('/c/shoes/men')->assertNotFound();
        $this->get('/c/shoes/men/sport')->assertNotFound();
        $this->get('/categories')->assertOk()->assertSee('أحذية');
    }

    public function test_database_categories_are_authoritative_even_when_they_have_no_products(): void
    {
        $this->seed(CategoryTreeSeeder::class);

        $leaf = $this->get('/c/shoes/men/sport')->assertOk();
        $this->assertTrue($leaf->viewData('products')->isEmpty());

        $gender = $this->get('/c/men')->assertOk();
        $this->assertTrue($gender->viewData('products')->isEmpty());
    }

    public function test_gender_aggregation_does_not_restore_demo_products_from_a_hidden_branch(): void
    {
        $this->seed(CategoryTreeSeeder::class);
        Category::query()->where('path', 'shoes/men')->update(['status' => 'hidden']);

        $response = $this->get('/c/men')->assertOk();
        $this->assertTrue($response->viewData('products')->isEmpty());
    }

    public function test_catalogue_query_excludes_products_beneath_hidden_ancestors(): void
    {
        $this->seed(CategoryTreeSeeder::class);
        $category = Category::query()->where('path', 'shoes/men/sport')->sole();
        Category::query()->where('path', 'shoes/men')->update(['status' => 'hidden']);
        $product = Product::query()->create([
            'name' => 'Hidden ancestor product', 'slug' => 'hidden-ancestor-product',
            'price' => 50, 'status' => 'active', 'source' => 'legacy',
        ]);
        ProductOffer::query()->create([
            'product_id' => $product->id, 'price' => 50, 'currency' => 'ILS',
            'stock' => 1, 'status' => 'active',
        ]);
        $product->categories()->attach($category);

        $this->assertTrue(app(CatalogQuery::class)->forCategoryPath('shoes/men')->isEmpty());
    }

    public function test_category_paths_are_canonicalized_and_bounded(): void
    {
        $this->seed(CategoryTreeSeeder::class);

        $this->get('/c/SHOES/MEN')->assertStatus(301)->assertRedirect('/c/shoes/men');
        $this->get('/c/shoes/'.str_repeat('a', 101))->assertNotFound();
    }

    private function flattenPaths(array $nodes, ?string $parentPath = null): array
    {
        $paths = [];

        foreach ($nodes as $slug => $node) {
            $path = $parentPath ? $parentPath.'/'.$slug : $slug;
            $paths[] = $path;

            if (is_array($node) && isset($node['children']) && is_array($node['children'])) {
                array_push($paths, ...$this->flattenPaths($node['children'], $path));
            }
        }

        return $paths;
    }
}

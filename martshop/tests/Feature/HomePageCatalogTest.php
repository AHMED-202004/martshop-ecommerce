<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_uses_active_database_root_categories_when_available(): void
    {
        $root = Category::query()->create([
            'name' => 'Visible Root', 'slug' => 'visible-root', 'path' => 'visible-root',
            'status' => 'active', 'sort_order' => 1,
        ]);
        Category::query()->create([
            'name' => 'Hidden Root', 'slug' => 'hidden-root', 'path' => 'hidden-root',
            'status' => 'hidden', 'sort_order' => 2,
        ]);
        Category::query()->create([
            'parent_id' => $root->id, 'name' => 'Visible Child', 'slug' => 'visible-child',
            'path' => 'visible-root/visible-child', 'depth' => 1, 'status' => 'active',
        ]);
        Category::query()->create([
            'parent_id' => $root->id, 'name' => 'Hidden Child', 'slug' => 'hidden-child',
            'path' => 'visible-root/hidden-child', 'depth' => 1, 'status' => 'hidden',
        ]);

        $response = $this->get(route('home'))
            ->assertOk()
            ->assertSee('Visible Root')
            ->assertSee(url('/c/visible-root'), false)
            ->assertSee('Visible Child')
            ->assertDontSee('Hidden Root')
            ->assertDontSee('Hidden Child')
            ->assertDontSee('data-cat="shoes"', false);
        $this->assertSame(
            [['title' => 'Visible Child', 'items' => [['Visible Child', url('/c/visible-root/visible-child')]]]],
            $response->viewData('megaMenu')['visible-root']['cols'],
        );
    }

    public function test_home_shows_only_known_brands_that_have_published_products(): void
    {
        $visibleBrand = Brand::query()->create(['name' => 'Adidas', 'slug' => 'adidas']);
        $hiddenBrand = Brand::query()->create(['name' => 'Reebok', 'slug' => 'reebok']);
        Brand::query()->create(['name' => 'Unknown Brand', 'slug' => 'unknown-brand']);

        Product::query()->create([
            'brand_id' => $visibleBrand->id, 'name' => 'Published brand item',
            'slug' => 'published-brand-item', 'price' => 10, 'status' => 'active',
            'source' => 'legacy', 'in_stock' => true,
        ]);
        Product::query()->create([
            'brand_id' => $hiddenBrand->id, 'name' => 'Hidden brand item',
            'slug' => 'hidden-brand-item', 'price' => 10, 'status' => 'hidden',
            'source' => 'legacy', 'in_stock' => true,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('brands.show', 'adidas'), false)
            ->assertDontSee(route('brands.show', 'reebok'), false)
            ->assertDontSee('unknown-brand');
    }

    public function test_home_keeps_legacy_category_navigation_before_database_import(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('action="'.route('search').'"', false)
            ->assertSee('name="q"', false)
            ->assertSee('data-cat="shoes"', false)
            ->assertSee('data-cat="books"', false);
    }

    public function test_home_does_not_expose_legacy_categories_when_fallback_is_disabled(): void
    {
        config()->set('catalog.legacy_fallback_enabled', false);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('data-cat="shoes"', false)
            ->assertDontSee('/c/shoes', false);
    }

    public function test_home_template_has_one_database_menu_payload_and_no_dead_mega_blocks(): void
    {
        $view = file_get_contents(resource_path('views/home.blade.php'));

        $this->assertIsString($view);
        $this->assertSame(1, substr_count($view, 'id="martCategoryMenuData"'));
        $this->assertStringNotContainsString('window.MART_CATEGORY_MENU', $view);
        $this->assertStringNotContainsString('window.MEGA', $view);
        $this->assertSame(substr_count($view, '@push('), substr_count($view, '@endpush'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_seeder_contains_foundation_data_but_no_demo_accounts_or_catalogue(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThan(0, Role::query()->count());
        $this->assertGreaterThan(0, Location::query()->count());
        $this->assertGreaterThan(0, Category::query()->count());
        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, Product::query()->count());
        $this->assertSame(0, ProductOffer::query()->count());
    }

    public function test_legacy_storefront_fallback_is_fail_closed_when_disabled(): void
    {
        config()->set('catalog.legacy_fallback_enabled', false);

        $this->get(route('categories.index'))
            ->assertOk()
            ->assertDontSee('/c/shoes', false);
        $this->get('/c/shoes/men/sport')->assertNotFound();
        $this->get('/p/adidas-ultimashow-2-grey')->assertNotFound();
        $this->post(route('cart.add'), [
            'slug' => 'adidas-ultimashow-2-grey',
            'qty' => 1,
        ])->assertSessionHasErrors('cart');
        $this->assertEmpty(session('cart.items', []));
    }
}

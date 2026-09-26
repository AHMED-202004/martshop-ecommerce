<?php

namespace Tests\Feature;

use App\Http\Controllers\CategoryController;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as LaravelRoute;
use Tests\TestCase;

class RouteArchitectureTest extends TestCase
{
    public function test_route_names_are_unique(): void
    {
        $names = collect(app('router')->getRoutes()->getRoutes())
            ->map(fn (LaravelRoute $route) => $route->getName())
            ->filter();

        $this->assertSame([], $names->duplicates()->values()->all());
    }

    public function test_men_and_women_routes_are_not_captured_by_the_category_catch_all(): void
    {
        foreach (['/c/men', '/c/women'] as $uri) {
            $route = app('router')->getRoutes()->match(Request::create($uri));
            $this->assertSame(CategoryController::class.'@gender', $route->getActionName());
            $this->get($uri)->assertOk();
        }
    }

    public function test_legacy_category_and_cart_links_remain_available(): void
    {
        $this->get('/c')->assertOk();
        $this->get('/cart')->assertOk();
    }
}

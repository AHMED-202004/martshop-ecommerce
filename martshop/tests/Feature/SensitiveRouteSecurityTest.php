<?php

namespace Tests\Feature;

use App\Http\Middleware\PrivateAccountResponse;
use App\Http\Middleware\PrivateFinancialResponse;
use Illuminate\Routing\Route;
use Tests\TestCase;

class SensitiveRouteSecurityTest extends TestCase
{
    public function test_sensitive_namespaces_require_authentication_and_private_response_headers(): void
    {
        foreach ($this->sensitiveRoutes() as $route) {
            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth', $middleware, $route->uri().' must require authentication.');
            $this->assertTrue(
                in_array(PrivateAccountResponse::class, $middleware, true)
                || in_array(PrivateFinancialResponse::class, $middleware, true),
                $route->uri().' must prevent caching of private responses.',
            );
        }
    }

    public function test_sensitive_mutations_are_rate_limited(): void
    {
        foreach ($this->sensitiveRoutes() as $route) {
            if (! array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
                continue;
            }

            $this->assertTrue(
                collect($route->gatherMiddleware())->contains(fn (string $middleware) => str_starts_with($middleware, 'throttle:')),
                $route->uri().' must be rate limited.',
            );
        }
    }

    /** @return list<Route> */
    private function sensitiveRoutes(): array
    {
        return collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn (Route $route) => preg_match('/\A(?:admin|merchant|delivery)(?:\/|\z)/', $route->uri()) === 1)
            ->values()
            ->all();
    }
}

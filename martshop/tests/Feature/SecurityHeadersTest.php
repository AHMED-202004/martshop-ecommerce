<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_receive_baseline_browser_security_headers(): void
    {
        $response = $this->get(route('home'))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("script-src 'self';", $csp);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
    }

    public function test_private_response_middleware_keeps_its_stricter_headers(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('my-account'));

        $response->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString("script-src 'self';", $response->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString(
            "script-src 'self' 'unsafe-inline'",
            $response->headers->get('Content-Security-Policy'),
        );
    }

    public function test_private_financial_pages_allow_only_same_origin_external_styles(): void
    {
        Route::middleware(\App\Http\Middleware\PrivateFinancialResponse::class)
            ->get('/_financial-header-test', fn () => response('ok'));

        $response = $this->get('/_financial-header-test')->assertOk();
        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'none'", $csp);
        $this->assertStringContainsString("style-src 'self' 'unsafe-inline'", $csp);
    }

    public function test_authenticated_forbidden_response_uses_safe_arabic_page(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('admin.settings.edit'));

        $response->assertForbidden()
            ->assertSee('غير مصرح لك بالدخول')
            ->assertSee(route('my-account'), false)
            ->assertDontSee('Illuminate\\')
            ->assertDontSee(base_path())
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_missing_page_uses_safe_arabic_page(): void
    {
        $this->get('/this-page-does-not-exist')
            ->assertNotFound()
            ->assertSee('الصفحة غير موجودة')
            ->assertSee(route('home'), false)
            ->assertDontSee('Illuminate\\')
            ->assertDontSee(base_path())
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_expired_session_error_view_has_only_trusted_navigation(): void
    {
        $html = view('errors.419')->render();

        $this->assertStringContainsString('انتهت الجلسة', $html);
        $this->assertStringContainsString(route('login'), $html);
        $this->assertStringContainsString(route('home'), $html);
        $this->assertStringNotContainsString('url()->previous', $html);
        $this->assertStringNotContainsString('Illuminate\\', $html);
        $this->assertStringNotContainsString(base_path(), $html);
    }

    public function test_server_error_views_do_not_render_internal_details(): void
    {
        foreach ([500 => 'تعذر إكمال الطلب', 503 => 'صيانة مؤقتة'] as $status => $message) {
            $html = view("errors.{$status}")->render();
            $this->assertStringContainsString($message, $html);
            $this->assertStringContainsString(route('home'), $html);
            $this->assertStringNotContainsString('exception', strtolower($html));
            $this->assertStringNotContainsString('Illuminate\\', $html);
            $this->assertStringNotContainsString(base_path(), $html);
        }
    }

    public function test_private_local_disk_does_not_publish_vendor_storage_routes(): void
    {
        $this->assertFalse(Route::has('storage.local'));
        $this->assertFalse(Route::has('storage.local.upload'));
    }

    public function test_invalid_or_excessive_pagination_is_rejected_before_querying(): void
    {
        foreach (['page=10001', 'page[]=1', 'products_page=not-a-number'] as $query) {
            $this->getJson(route('search').'?'.$query)
                ->assertUnprocessable();
        }

        $this->get(route('search').'?q=missing&page=10000')->assertOk();
    }

    public function test_trusted_host_patterns_are_exact_and_do_not_allow_arbitrary_subdomains(): void
    {
        $patterns = array_map(
            fn (string $host) => '^'.preg_quote($host, '/').'$',
            config('app.trusted_hosts'),
        );
        Request::setTrustedHosts($patterns);

        try {
            $this->assertSame('127.0.0.1', Request::create('http://127.0.0.1/')->getHost());
            Request::create('http://attacker.example/')->getHost();
            $this->fail('An unconfigured host must be rejected.');
        } catch (SuspiciousOperationException) {
            $this->addToAssertionCount(1);
        } finally {
            Request::setTrustedHosts([]);
        }
    }
}

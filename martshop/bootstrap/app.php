<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\ValidatePaginationParameters;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustHosts(
            at: fn () => array_map(
                fn (string $host) => '^'.preg_quote($host, '/').'$',
                config('app.trusted_hosts', []),
            ),
            subdomains: false,
        );
        $middleware->append(ValidatePaginationParameters::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->appendToGroup('web', EnsureAccountIsActive::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['token', 'current_password', 'password', 'password_confirmation', 'card_number', 'exp', 'cvv', 'identity_number', 'phone', 'date_of_birth', 'address', 'legal_name', 'business_type', 'contact', 'ref', 'message', 'body', 'topic', 'account_identifier', 'account_name', 'provider_name', 'reference_number', 'transaction_reference', 'sender_name', 'sender_account', 'amount', 'transferred_at', 'idempotency_key', 'reason', 'note', 'notes', 'assignment_notes', 'instructions', 'review_notes', 'recipient_snapshot', 'cancellation_reason', 'first_name', 'last_name', 'governorate', 'city', 'mobile', 'alt_mobile', 'gender', 'dob_day', 'dob_month', 'dob_year', 'register_login', 'email', 'login']);
        $exceptions->shouldRenderJsonWhen(fn ($request, $exception) => $request->is('api/*') || $request->expectsJson());
    })->create();

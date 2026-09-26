<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProductionReadinessCommandTest extends TestCase
{
    public function test_secure_production_configuration_passes(): void
    {
        $this->setSecureProductionConfig();

        $this->artisan('app:production-readiness')
            ->expectsOutput('Production readiness checks passed.')
            ->assertSuccessful();
    }

    public function test_unsafe_configuration_fails_without_printing_secrets(): void
    {
        $this->setSecureProductionConfig();
        config()->set([
            'app.debug' => true,
            'app.url' => 'http://shop.example.test',
            'app.key' => 'secret-key-that-must-not-be-printed',
            'session.secure' => false,
            'queue.default' => 'sync',
        ]);

        $this->artisan('app:production-readiness')
            ->expectsOutput('Production readiness checks failed:')
            ->expectsOutputToContain('APP_DEBUG must be false.')
            ->expectsOutputToContain('APP_URL must be a valid HTTPS URL.')
            ->expectsOutputToContain('SESSION_SECURE_COOKIE must be true.')
            ->expectsOutputToContain('QUEUE_CONNECTION must use an asynchronous backend.')
            ->doesntExpectOutputToContain('secret-key-that-must-not-be-printed')
            ->assertFailed();
    }

    public function test_enabled_recovery_rejects_non_delivery_mailer_or_insecure_url(): void
    {
        $this->setSecureProductionConfig();
        config()->set([
            'password_recovery.mail_enabled' => true,
            'password_recovery.url' => 'http://shop.example.test/reset-password',
            'mail.default' => 'log',
        ]);

        $this->artisan('app:production-readiness')
            ->expectsOutputToContain('Enabled password recovery requires a real mail transport and HTTPS reset URL.')
            ->assertFailed();
    }

    private function setSecureProductionConfig(): void
    {
        config()->set([
            'app.env' => 'production',
            'app.debug' => false,
            'app.url' => 'https://shop.example.test',
            'app.timezone' => 'UTC',
            'app.key' => 'base64:test-key',
            'app.trusted_hosts' => ['shop.example.test'],
            'session.driver' => 'database',
            'session.encrypt' => true,
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
            'queue.default' => 'database',
            'queue.connections.database.after_commit' => true,
            'catalog.legacy_fallback_enabled' => false,
            'cors.allowed_origins' => [],
            'password_recovery.mail_enabled' => false,
        ]);
    }
}

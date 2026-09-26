<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckProductionReadiness extends Command
{
    protected $signature = 'app:production-readiness';

    protected $description = 'Fail when security-critical production configuration is unsafe';

    public function handle(): int
    {
        $url = (string) config('app.url');
        $urlHost = parse_url($url, PHP_URL_HOST);
        $trustedHosts = config('app.trusted_hosts', []);
        $queue = (string) config('queue.default');
        $corsOrigins = config('cors.allowed_origins', []);
        $failures = array_filter([
            config('app.env') === 'production' ? null : 'APP_ENV must be production.',
            config('app.debug') === false ? null : 'APP_DEBUG must be false.',
            parse_url($url, PHP_URL_SCHEME) === 'https' && is_string($urlHost) && $urlHost !== ''
                ? null : 'APP_URL must be a valid HTTPS URL.',
            config('app.timezone') === 'UTC' ? null : 'APP_TIMEZONE must be UTC.',
            filled(config('app.key')) ? null : 'APP_KEY must be configured.',
            $this->trustedHostsAreSafe($trustedHosts, $urlHost) ? null
                : 'TRUSTED_HOSTS must contain the APP_URL host and only exact hostnames.',
            config('session.driver') === 'database' ? null : 'SESSION_DRIVER must be database.',
            config('session.encrypt') === true ? null : 'SESSION_ENCRYPT must be true.',
            config('session.secure') === true ? null : 'SESSION_SECURE_COOKIE must be true.',
            config('session.http_only') === true ? null : 'SESSION_HTTP_ONLY must be true.',
            in_array(config('session.same_site'), ['lax', 'strict'], true) ? null
                : 'SESSION_SAME_SITE must be lax or strict.',
            ! in_array($queue, ['sync', 'null'], true) ? null
                : 'QUEUE_CONNECTION must use an asynchronous backend.',
            config("queue.connections.{$queue}.after_commit") === true ? null
                : 'The selected queue connection must dispatch after commit.',
            config('catalog.legacy_fallback_enabled') === false ? null
                : 'LEGACY_CATALOG_FALLBACK_ENABLED must be false.',
            $this->corsOriginsAreSafe($corsOrigins) ? null
                : 'API_CORS_ALLOWED_ORIGINS may contain only exact HTTPS origins.',
            $this->passwordRecoveryMailIsSafe() ? null
                : 'Enabled password recovery requires a real mail transport and HTTPS reset URL.',
        ]);

        if ($failures !== []) {
            $this->error('Production readiness checks failed:');
            foreach ($failures as $failure) {
                $this->line('- '.$failure);
            }

            return self::FAILURE;
        }

        $this->info('Production readiness checks passed.');

        return self::SUCCESS;
    }

    private function trustedHostsAreSafe(mixed $hosts, mixed $urlHost): bool
    {
        if (! is_array($hosts) || ! is_string($urlHost) || $urlHost === '' || ! in_array($urlHost, $hosts, true)) {
            return false;
        }

        foreach ($hosts as $host) {
            if (! is_string($host) || $host === '' || filter_var('https://'.$host, FILTER_VALIDATE_URL) === false
                || str_contains($host, '*') || str_contains($host, '/') || str_contains($host, ':')) {
                return false;
            }
        }

        return true;
    }

    private function corsOriginsAreSafe(mixed $origins): bool
    {
        if (! is_array($origins)) {
            return false;
        }

        foreach ($origins as $origin) {
            if (! is_string($origin) || parse_url($origin, PHP_URL_SCHEME) !== 'https'
                || ! is_string(parse_url($origin, PHP_URL_HOST)) || str_contains($origin, '*')) {
                return false;
            }
        }

        return true;
    }

    private function passwordRecoveryMailIsSafe(): bool
    {
        if (! config('password_recovery.mail_enabled')) {
            return true;
        }

        return ! in_array(config('mail.default'), ['log', 'array'], true)
            && parse_url((string) config('password_recovery.url'), PHP_URL_SCHEME) === 'https';
    }
}

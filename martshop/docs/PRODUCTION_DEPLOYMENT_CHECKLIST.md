# Production deployment checklist

This file is a release gate, not evidence that the current local installation is production-ready.

## Required environment

- Set `APP_ENV=production`, `APP_DEBUG=false`, a stable HTTPS `APP_URL`, and an exact comma-separated `TRUSTED_HOSTS` list. Add only hostnames actually served by this deployment; subdomains are not trusted implicitly.
- Keep the application/storage timezone on UTC. If local Palestine time is required in production UI, implement an explicit display/input conversion and test daylight-saving boundaries before changing the labels or accepting real financial timestamps.
- Generate and protect a unique `APP_KEY`; never copy the local key or commit a real `.env` file.
- Use `SESSION_DRIVER=database`, `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, and `SESSION_SAME_SITE=lax`.
- Keep `LEGACY_CATALOG_FALLBACK_ENABLED=false`. Keep withdrawals and automatic settlement release disabled until their operating controls are approved.
- Configure a real database, cache and queue. Use separate least-privilege credentials and do not expose their ports publicly.
- Keep `QUEUE_AFTER_COMMIT=true` so asynchronous work cannot observe financial or order state before its database transaction commits.
- Configure trusted proxies only for the actual load balancer/CDN addresses. Verify the application sees the real client IP and HTTPS scheme before relying on rate limits or secure cookies.
- Keep `API_CORS_ALLOWED_ORIGINS` empty unless a reviewed browser client needs the public read-only API; when needed, list only its exact HTTPS origins.
- Set `PASSWORD_RESET_MAIL_ENABLED=true` only after a real mail transport and trusted HTTPS `PASSWORD_RESET_URL` pass recovery and staff-invitation delivery tests. Prevent access logs from recording reset/invitation query strings.

## Release procedure

1. Back up the database and private storage, then test that the backup can be restored.
2. Run `composer install --no-dev --classmap-authoritative` and `npm ci && npm run build` from the reviewed lock files.
3. Run `php artisan app:production-readiness`. It must pass after production environment variables are loaded; it reports unsafe setting names without printing their values.
4. Run `php artisan migrate --force`, then the normal foundation seeder only when required. Never run `DemoDatabaseSeeder` against production.
5. Run `php artisan optimize` and restart long-running queue workers with `php artisan queue:restart`.
6. Configure one scheduler invocation per minute and supervised queue workers. Confirm failed jobs and scheduler failures are monitored.
7. Restrict the web-server document root to `public/`. Deny direct HTTP access to `.env`, `storage/`, database files, source, logs and backups.
8. Set PHP `expose_php=Off` and remove server/version banners at the reverse proxy; the built-in development server is not a production web server.

## Verification before traffic

- Run the full PHPUnit suite, `composer audit --locked`, `npm audit --package-lock-only`, and `npm run build` on the release artifact.
- Verify HTTPS redirect/HSTS at the edge, secure session-cookie attributes, real client IP handling, CSRF behavior, 429 responses, and private `Cache-Control: no-store` headers.
- Storefront and private-account responses now enforce `script-src 'self'` without `unsafe-inline`; all executable Blade scripts were moved to same-origin assets, and dynamic catalogue payloads use escaped HTML data attributes. Password recovery also enforces same-origin-only styles. Runtime JavaScript no longer generates inline styles, Blade style attributes are eliminated, and the remaining Blade inventory is 21 style blocks. Before removing `unsafe-inline` from the remaining storefront, account and financial `style-src` policies, move or nonce/hash-authorize those remaining page-specific blocks.
- Test login, password recovery, customer checkout, merchant moderation, proof access, refunds, withdrawals and delivery with least-privilege test accounts.
- Exercise concurrent financial transitions on the production database engine; SQLite in-memory tests do not prove deadlock or worker-crash behavior.
- Test antivirus/content scanning and retention controls for uploaded KYC and financial evidence before accepting real documents.
- Confirm logs and error monitoring redact credentials, reset tokens, PINs, payout destinations and uploaded private content.

## Rollback

Keep the previous application artifact and a migration-aware rollback plan. Database rollback is not automatically safe after real orders or financial transitions; prefer a forward fix unless a reviewed restoration procedure explicitly permits rollback.

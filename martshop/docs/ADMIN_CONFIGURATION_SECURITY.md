# Administrator configuration security — 2026-09-08

The marketplace settings and commission-rule screens now use the strict private-financial response middleware and private layout. Their responses are non-cacheable, send no referrer, deny framing and MIME sniffing, and use the restricted financial Content Security Policy without third-party page assets.

## Reauthentication and authorization

Every settings save and every commission-rule create or update requires the current Mart.ps administrator password through the authenticated `web` guard. The password is validation-only: it is not passed into either service or stored in settings, commission rules, audit logs, or flashed form input. The free-form settings change reason is also excluded from flashed validation data.

Form requests continue to enforce `settings.manage` or `commissions.manage`. The settings and commission services now repeat those permission checks before opening their transactions, so direct internal service calls cannot bypass the HTTP authorization layer.

## Financial behavior

No existing marketplace setting or commission rule was changed while adding these controls. Commission edits apply only when pricing a future order; the commission snapshot already stored on an earlier merchant order remains unchanged. Settlement dispute-day changes likewise do not shorten deadlines already snapshotted on delivered shipments.

These controls do not enable automatic settlement or withdrawals. Both remain separate operating decisions and withdrawals remain disabled.

## Public feature flags

Disabling new merchant registration now removes the merchant-registration links for customers without a merchant record and replaces the direct profile form with an operational notice. Existing merchants retain access to their established account. The public settings API exposes the same merchant-registration and support-widget flags, plus the public WhatsApp value, so future mobile clients can follow the administrator's current controls.

Disabling customer registration now removes the registration tab and sends direct registration-tab visits back to the sign-in form with a clear operational notice. Disabling new orders publishes a storefront-wide notice and removes the cart checkout form while leaving cart review and editing available. Registration and checkout remain independently rejected at their server-side boundaries.

The support control is labeled as the support window rather than live chat because the current widget routes to the persisted contact form.

## Verification

Focused settings, commission, dashboard and delivery coverage passed: **38 tests, 496 assertions**. The complete suite passed: **204 tests, 2093 assertions**. Blade compilation and PHP syntax checks passed, and all **143 routes** have unique names and method/URI signatures.

Tests use SQLite `:memory:`. No live setting, commission rule, order snapshot, ledger entry, settlement, payment or withdrawal was created or changed. Production-engine concurrency, HTTPS/session configuration and operational access-review procedures remain deployment requirements.

Latest focused settings/merchant/API/account coverage passed: **31 tests, 406 assertions**. The latest complete suite passed: **282 tests, 3040 assertions**; Blade/JavaScript checks passed and all **146 routes** retain unique names and method/URI signatures. No working/live settings were changed.

Latest feature-flag/cart/account focused coverage passed across two runs: **42 tests, 400 assertions**. The latest complete suite passed: **282 tests, 3049 assertions**; Blade/JavaScript checks passed and all **146 routes** retain unique names and method/URI signatures. No working/live settings were changed.

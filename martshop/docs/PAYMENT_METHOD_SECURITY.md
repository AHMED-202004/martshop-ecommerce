# Manual payment-method security — 2026-09-08

The administrator payment-method page contains the platform's manual-transfer account identifiers and instructions. It now uses the strict private-financial response middleware and layout, so responses are non-cacheable, send no referrer, deny framing and MIME sniffing, and use the restricted financial Content Security Policy without third-party page assets.

## Reauthentication and authorization

Creating or updating a payment method requires the current Mart.ps administrator password through the authenticated `web` guard. The permission remains `payment-methods.manage`, and the payment-method service now repeats that check before opening a transaction. Direct internal service calls therefore cannot bypass the HTTP authorization layer.

The password, account name, account identifier and transfer instructions are excluded from flashed validation input. Failed reauthentication leaves payment methods and audit logs unchanged. Mutation routes are rate limited to ten attempts per minute per applicable Laravel throttle key.

## Audit-data minimization

New payment-method audit records contain only the method name, slug, type, active state and sort order. Updates record the boolean fact that account details changed, but never copy the account name, identifier or transfer instructions into the audit snapshot.

Historical audit rows were not rewritten or deleted. Their administrative rendering continues to apply the existing defensive key redaction, but any historical raw records created before this change should be considered during retention and database-access review.

## Financial behavior and verification

No existing payment method, account identifier, payment, order or activation state was changed. Customer payment snapshots and earlier orders remain untouched.

Focused payment workflow, proof, review and dashboard coverage passed: **25 tests, 287 assertions**. The complete suite passed: **205 tests, 2120 assertions**. Blade compilation and PHP syntax checks passed. All **143 routes** retain unique names and method/URI signatures.

Tests use SQLite `:memory:` and fake private storage where applicable. Production operation still requires HTTPS, correct proxy/session configuration, tightly controlled administrator accounts and review of historical database/audit access.

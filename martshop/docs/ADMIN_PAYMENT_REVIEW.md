# Admin payment review — 2026-09-08

The manual-payment review pages now use the strict private-financial response middleware and layout. Both the queue and individual review page receive `no-store, private`, `no-referrer`, frame denial, MIME-sniffing protection and the restricted financial CSP. The authorized administration menu remains available inside that private layout without loading third-party assets.

## Decision safeguards

Accepting, rejecting or marking a transfer short now requires the reviewer's current account password. The password is validated through Laravel's authenticated `web` guard and is excluded from flashed validation input. This is a reauthentication control only: it does not request or handle a bank password, wallet password or OTP.

A reviewer cannot decide a payment belonging to the same user account. This separation is enforced inside the locked transactional service rather than only in the interface, so a crafted request cannot bypass it. A blocked self-review leaves the payment, order and audit log unchanged. Existing authorization, row locking, optimistic `lock_version`, amount checks, idempotent repeat decisions and audit behavior remain in place.

The review page links to the existing protected proof-download endpoint instead of exposing storage paths. That endpoint independently verifies authorization, path confinement, size and SHA-256 integrity before returning an attachment.

## Verification

Four new feature tests cover private response headers and pre-disclosure authorization, failed password reauthentication without secret flashing or writes, self-review rejection without state changes, and a scoped read-only pending queue. The focused payment/commission/dashboard suite passed: **23 tests, 275 assertions**. The complete suite passed: **194 tests, 1881 assertions**. Blade compilation and PHP syntax checks passed.

Tests use SQLite `:memory:` and fake private storage where applicable. No live payment was accepted, rejected or otherwise changed during this work. Production operation still requires HTTPS, correct proxy/session configuration, controlled reviewer accounts and monitoring of financial audit events.

Later protection of the manual-transfer account configuration itself is documented in [PAYMENT_METHOD_SECURITY.md](PAYMENT_METHOD_SECURITY.md).

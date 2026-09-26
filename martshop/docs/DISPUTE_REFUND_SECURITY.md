# Dispute, refund-review and settlement security — 2026-09-08

Customer dispute/refund submissions and administrator refund/settlement pages now use the strict private-financial response middleware. Responses receive `no-store, private`, `no-referrer`, frame denial, MIME-sniffing protection and the restricted financial CSP. The administrator pages use the private financial layout without third-party assets.

## Administrative reauthentication and account separation

Closing a delivery dispute now requires the current Mart.ps staff password through the authenticated `web` guard. Opening an administrative hold also requires the staff password; a normal customer opening a dispute for their own eligible delivery is not asked for a second password.

An administrator cannot use staff authority to close a dispute belonging to their own customer order. That restriction is enforced inside the delivery-row transaction, not only in the interface. Own deliveries and own refund claims are omitted from that reviewer's settlement and refund queues. If an administrator is also the customer, dispute-opening eligibility follows the normal customer deadline instead of extending it through staff privileges.

Refund decisions retain their existing independent current-password check and service-level self-review restriction. A refund approval still does not transfer money or close the dispute, and dispute closure still does not issue a refund or immediately release funds.

## Sensitive validation input

Free-form `reason` input is excluded from flashed validation data alongside current passwords and the previously protected financial fields. Failed password validation therefore leaves dispute, refund, ledger and audit state unchanged without copying the submitted reason into session-backed old input.

## Verification

Three additional feature tests cover private refund/settlement responses and pre-disclosure authorization, own-claim queue exclusion, password-protected closure with session privacy, service-level self-closure denial, and password-protected administrative holds. The focused refund/transfer/destination/delivery/dashboard suite passed: **79 tests, 963 assertions**. The complete suite passed: **202 tests, 2054 assertions**. Blade compilation and PHP syntax checks passed, and all 143 routes have unique names and method/URI signatures.

Tests use SQLite `:memory:`. No live dispute, refund, settlement, ledger entry, payment or transfer was created or changed. Automatic settlement release and withdrawals remain disabled; production-engine concurrency, HTTPS/session configuration and operating review procedures remain deployment requirements.

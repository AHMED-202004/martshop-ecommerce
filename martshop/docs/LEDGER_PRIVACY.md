# Ledger privacy and presentation — 2026-09-08

The merchant and administrator ledger pages now use the strict private-financial response middleware and layout. Their responses receive `no-store, private`, `no-referrer`, frame denial, MIME-sniffing protection and the restricted financial CSP. Authorization is evaluated before an unauthorized user can receive ledger content, and the merchant query remains constrained through that merchant's relationship.

## Data minimization

Ledger list queries select only fields rendered by each page. They no longer eager-load payment provider references, merchant-order data or ledger metadata that the interface does not use. The administrator's selected-merchant lookup retrieves only the merchant ID and legal name rather than the full merchant/KYC model.

Administrator pagination preserves only the validated `merchant_id` and `status` filters. Unexpected query parameters are not copied into subsequent page links. Pagination remains deterministic by newest ledger-entry ID and continues to use 50 rows for administration and 40 rows for a merchant.

## Presentation

Amounts use each ledger entry's stored currency instead of assuming ILS. ILS retains the shekel symbol; other currencies display their three-letter currency code. Balance cards display every stored currency bucket, with an ILS zero only when a bucket has no entries. Entry types and balance states now have Arabic interface labels, and tables remain horizontally scrollable on narrow screens.

The underlying append-only behavior was not changed: ledger entries still reject updates and deletion, and this batch adds no endpoint that mutates financial records.

## Verification

One additional feature test and expanded existing assertions cover strict private headers, cross-merchant isolation, pre-disclosure administrator authorization, currency-aware rendering, exclusion of metadata and foreign references, allowlisted pager filters, and read-only behavior. The focused commission/ledger/withdrawal/dashboard suite passed: **24 tests, 393 assertions**. The complete suite passed: **199 tests, 1994 assertions**. Blade compilation and PHP syntax checks passed, and all 143 routes have unique names and method/URI signatures.

Tests use SQLite `:memory:`. No working ledger, balance, order, payment, withdrawal or merchant data was modified. Production-engine concurrency and operational financial reconciliation remain release requirements.

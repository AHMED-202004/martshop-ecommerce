# Refund transfer queue — 2026-09-02

The existing `/admin/refund-transfers` page now supports operational filtering and summaries. This is read-only monitoring, not bank reconciliation, financial closing, automatic payment, or a new approval decision.

## Included

- Existing `refunds.pay` authorization and private financial response headers remain required. No new permissions or migration.
- Search by full/partial refund reference; `%`, `_` and `!` are literal characters, not user-controlled SQL wildcards. Search input is bound and HTML-escaped.
- Filter approved, processing or paid refunds; requested/rejected requests remain in the separate review workflow. Optionally show only refunds with at least one cancelled preparation attempt. That history filter does not mean the refund request itself was cancelled.
- Inclusive refund-request creation dates in the configured application timezone, with independent start/end bounds. These are not transfer payment dates. Sort by creation date, then ID for deterministic pagination; newest first by default.
- Thirty refunds per page. Validated filters survive pagination; unknown query keys are not propagated.
- Count and sum all matching parent refunds, grouped by **status and currency**, independent of pagination. Multiple cancelled attempts never multiply amounts. Amounts are customer refund totals, not merchant net or proof that a bank payment settled.
- Display destination verification/preparation status, current attempt ID/time, and the count of cancelled preparations. An unrecorded proof warns staff to check externally before another action. Detail pages remain the authority for preparation/recording checks.
- The list does not load recipient account snapshots, cancellation reasons, payment references or proof paths. Selective eager loading and an aggregate cancellation count avoid per-row queries.

## Verification

`php artisan test --compact`: **151 passed, 1448 assertions**. The focused refund-transfer suite passes 26 tests / 314 assertions.

Six additional feature tests cover status/currency grouping; exclusion of requested/rejected refunds; cancellation-history deduplication; combined filters and whole end-day inclusion; literal search and escaped HTML; permission/validation/private headers; no financial/audit writes; pagination/order and full-result summaries; destination verification labels; and a bounded five refund-table SELECTs for a 30-row page. Financial fixtures exist only in SQLite `:memory:`.

The working database was checked read-only: 771 products, 710 offers, 23 orders, 23 merchant orders, zero refund requests/transfers/ledger entries, and zero foreign-key violations. The new query runs successfully against it with an empty result. Withdrawals and automatic settlement remain disabled.

Blade compilation and PHP syntax checks passed. No browser-driven responsive/visual verification, production-engine concurrency/load test, or external financial verification was performed. These are current operational queries, not a locked financial closing snapshot; concurrent changes can occur while operators review the page. Large-dataset indexing/summary-table work remains a deployment consideration.

# Refund review foundation — 2026-09-02

This batch implements **requests and review only**, not refund execution. It adds no payment integration, outgoing transfer, customer wallet credit, inventory return, or refund ledger entry.

## Customer and staff workflow

- From `/order-history`, the owner of a delivered, paid, disputed suborder can request review of a **full suborder refund including its allocated delivery and service fees**. This is an explicit requested scope, not an automatic general return-policy decision. Partial/item refunds and post-settlement refunds remain unsupported.
- Amounts come from the stored suborder, never a browser-provided amount. The immutable review snapshot separates products, delivery, service, commission, merchant net, and customer total.
- Eligibility requires an open dispute, an unreleased delivery, a matching accepted payment and sale, and sale-specific held funds. Missing payment links, inconsistent currencies/amounts, and mismatched balances fail closed.
- One request per dispute is enforced by a unique database constraint. Repeated submissions return the original request without changing its reason or snapshot.
- `/admin/refunds` requires `refunds.review`, granted by `AuthorizationSeeder` to the existing admin role. No customer or delivery-worker role receives this permission. Review authorization is also enforced in the service; staff may not review their own purchase.
- Approval/rejection requires a reason, the current staff password, a fresh version, and a valid transition. Decision reasons are visible to the customer; the interface warns staff not to enter sensitive information.
- Approval rechecks the financial snapshot. Repeating the same decision is idempotent; changing a terminal decision is not supported in this phase.
- Both requested and approved refunds prevent dispute closure. Rejection does **not** release the hold or close the dispute: authorized settlement staff must separately review and close it.
- Audit recording and state changes are in one transaction. Failure rolls back the request/review. Request/review/closure share the delivery row lock. No files or external transfers occur in those transactions.

## Financial boundary and next step

For products 100.00, delivery 20.00, commission 5.00, the requested customer refund is 120.00, while the merchant's held net is 95.00. This phase does not debit either amount or reverse platform/delivery revenue. The UI explicitly distinguishes **approved, not transferred** from a completed refund.

Subsequent work adds [recipient verification](REFUND_DESTINATIONS.md) and [manual transfer recording](REFUND_TRANSFERS.md), including a frozen encrypted recipient, independent permission/reauthentication, private proof/reference, merchant-ledger movements and separate refund-component amounts. Do not infer a refund destination from the platform account that originally received the payment.

No cancellation/reopening of a reviewed request, return-stock handling, partial refunds, customer wallet, post-settlement recovery, or production-engine concurrency/crash testing is included here. Approved requests remain held until the separate transfer-recording workflow completes.

## Verification

- Full suite: **108 tests passed, 951 assertions** (including 9 new refund tests / 98 assertions).
- Tests cover trusted amounts, duplicate submissions/decisions, ownership, self-review prevention, permission checks, password/version/decision validation, closure blocking, rejection retaining holds, amount/ledger mismatch, audit rollback, snapshot immutability, and escaped customer text.
- `php artisan view:cache` passes. New routes appear in `route:list`; existing route-name uniqueness test passes. Browser-level visual testing was not performed for this batch.
- Migration `2026_09_02_000017_create_refund_review_requests.php` creates only `refund_requests`. Its rollback refuses to remove a non-empty review history.
- Trial migration, authorization seed, empty-table rollback, and foreign-key check passed on a byte-matched database copy before live application. Live comparison against the immediate backup verified all rows unchanged in products (771), offers (710), orders (23), suborders (23), deliveries (0), disputes (0), payments (0), and ledger entries (0). Refund requests remain empty; no financial operation was performed on live data.

Immediate pre-migration backup:

`database/backups/database-before-refund-review-20260902-112221.sqlite`

SHA-256: `c866397dffa017b325288e808681813dd2053a337a7afc7ac3966afe9bed3cd9`

Backup copying used a short SQLite reserved lock and matching source/copy hashes, releasing the source lock with rollback. The backup is local; it is not an encrypted off-site recovery solution.

Later response privacy, administrative dispute reauthentication and service-level self-closure protections are documented in [DISPUTE_REFUND_SECURITY.md](DISPUTE_REFUND_SECURITY.md).

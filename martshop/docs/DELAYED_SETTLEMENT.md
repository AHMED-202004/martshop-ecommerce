# Delayed settlement and delivery disputes

Implemented 2026-09-02. This replaces the former immediate release on delivery confirmation.

## Operating policy

- Default dispute period: **7 days**, configurable from 1 to 90 in `/admin/settings`. This is an initial configurable policy, not a legal recommendation.
- **Automatic release defaults to off**; withdrawals are independently disabled. Neither was enabled during implementation.
- Confirmation snapshots both the number of days and an absolute deadline on the delivery. Future policy edits do not shorten existing deadlines.
- Confirmation no longer creates settlement ledger entries. A missing sale entry does not undo evidence of a completed delivery; subsequent financial release fails closed and requires review.
- `settlements:release-due --limit=100` releases due, paid, confirmed deliveries with proof, no open dispute, and matching sale-specific pending balance. It checks the enable flag itself and is scheduled every five minutes with `withoutOverlapping`.
- Adding a Laravel schedule does not install/start a scheduler on the host. Configure and verify the actual host scheduler before enabling release. Disabled scheduling is safe: funds remain unavailable.

## State and accounting

Accepted payment creates the original pending sale. Release appends a pending debit and available credit, atomically with `settled_at`; it never rewrites the original sale. Unique keys and delivery row locks serialize release against dispute operations. An adjusted or inconsistent sale balance cannot borrow another order's funds.

Before the deadline, the customer may open one dispute for their own delivered shipment from order history. Finance staff with `settlements.manage` may also hold an overdue but unreleased shipment. Opening a dispute atomically moves that sale from pending to held. Retrying the same open dispute does not duplicate entries.

The `/admin/settlements` page shows delivered shipments, deadlines, held/released status and disputes. Only authorized finance staff may close a dispute, with a reason. Closing returns held funds to pending, preserving the original deadline. It does not pay a merchant or issue a customer refund. After the deadline, an enabled scheduler can release the now-eligible amount.

## Deliberate limits

- One dispute per delivery; no reopening after closure in this first workflow.
- Subsequent work adds [refund reviews](REFUND_REVIEWS.md) and [manual transfer recording](REFUND_TRANSFERS.md). Paid refunds are excluded from settlement and use a distinct refunded dispute state; unresolved refunds block ordinary closure. Partial resolutions, dispute evidence uploads and complaints after settlement remain outside this workflow. Do not use the close action as a substitute for a refund.
- Historical deliveries without the new deadline remain ineligible. No retrospective deadlines, ledger reversals or release of past transactions are performed by the migration.
- Each side-effecting delivery-confirmation transaction now runs once, without automatic database retries that could orphan files. A failed confirmation cleans its uploaded file; clients can retry through existing idempotency checks.
- Tests exercise sequential idempotency/locking paths on SQLite, not production-engine concurrent sessions or crash recovery.

## Verification and migration

Full suite: **99 tests, 853 assertions**. Covers early/exact deadline release, default-off behavior, snapshot stability, customer ownership, late customer disputes, staff closure, idempotent accounting, held balances, balance mismatch and rollback of failed release without removing delivery proof.

Migration `2026_09_02_000016` was tested on a byte-matched copy, including rollback, before live application. No existing financial data was modified. Backup:

`database/backups/database-before-delayed-settlement-20260902-110847.sqlite`

SHA-256: `962963ef2b77686d0b7f0ecd6a2886ccd45be60ab00e6001b633557c0354214b`

The Windows hash utility encountered a shared-file lock. The consistent copy was taken under a short SQLite `BEGIN IMMEDIATE` lock with PHP file copying and matching SHA-256 hashes; the lock was released with `ROLLBACK` without changing source data.

Later private-response, staff reauthentication and own-customer conflict protections are documented in [DISPUTE_REFUND_SECURITY.md](DISPUTE_REFUND_SECURITY.md).
Later administrator settings reauthentication, response privacy and service-level authorization are documented in [ADMIN_CONFIGURATION_SECURITY.md](ADMIN_CONFIGURATION_SECURITY.md).

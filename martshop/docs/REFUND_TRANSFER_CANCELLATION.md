# Unsent refund preparation cancellation — 2026-09-02

This cancels an internal preparation, **not a bank/wallet transfer**. The application cannot independently discover whether a transfer was sent, is pending, or settled outside the site. Operators must reconcile externally before using this action. Unknown/in-flight transfers must remain blocked; never prepare a replacement merely because recording failed.

## Workflow and authorization

- Open the existing `/admin/refund-transfers` detail screen. A prepared, unpaid attempt offers cancellation only to staff with both `refunds.pay` and the new `refunds.cancel` permission. The existing admin role receives the permission through `AuthorizationSeeder`; customers cannot cancel their own refund even with admin permissions.
- Enter the current Mart.ps password, a reason (5–2000 characters), and explicitly attest that the transfer was not sent/executed and is not pending. Reason validation and attestation also apply inside the service; password reauthentication is enforced at the HTTP boundary.
- The endpoint targets the exact attempt ID and checks the current parent version. Any recorded payment actor/time, external time/reference or proof metadata blocks cancellation, including partial/anomalous records. Financial snapshot/held-balance checks and an explicit refund-ledger-key check must pass.
- Cancellation restores the parent refund to `approved` and increments its version. It does not change held balances, release the dispute, append ledger entries, alter original payments, or remove proofs.
- The customer can then revoke/replace the destination through existing controls. Any new destination must be independently verified before preparing again. A replacement attempt rechecks the full financial snapshot, payment cap and current destination ID/version.

## History, locking and retries

- `RefundRequest::transfer()` returns only the non-cancelled active/paid attempt; `transfers()` retains all attempts. Paid attempts continue reserving the unique active key. Cancelled attempts no longer consume the payment's preparation budget.
- Order → delivery → refund → attempt locking serializes preparation, payment recording and cancellation. Cancellation, parent state and audit insertion are one database transaction. An audit failure restores the entire preparation state.
- A repeat cancellation targets only that already-cancelled attempt and is a no-op even if a replacement is now prepared or paid. It never cancels the replacement. Paid forms now require `transfer_id`, so a stale form cannot apply to a replacement even if its parent version is substituted.
- Prepared recipient/amount snapshots and terminal paid/cancelled records cannot be edited/deleted through the model. Cancellation reason is encrypted at rest, hidden from serialization and excluded from validation old-input flashing. The general audit records attempt/refund IDs and attestation, not the private reason; authorized staff can read the reason in the protected, paginated history. Cancelled recipient account identifiers are not redisplayed in history.
- Private-page headers, server-side escaped rendering, CSRF protection and action throttling remain in place.

## Schema and data preservation

Migration `2026_09_02_000020_add_refund_transfer_cancellation.php` adds unique nullable `active_key`, cancellation actor/time and encrypted-reason storage. Existing prepared/paid rows receive `refund:{refund_request_id}`. The old unique refund association becomes an ordinary index to preserve multiple attempts; an active-key uniqueness constraint prevents duplicate active attempts.

Rollback refuses any non-empty transfer history. On an empty table it restores the old unique association and removes the new fields. The in-memory migration test reconstructs the previous schema with prepared and paid fixtures, verifies every original field survives the upgrade, checks foreign keys and rejects duplicate active attempts.

The scoped migration, seeder, foreign-key check and empty rollback passed on a byte-matched trial copy:

`C:/Users/MSI/Documents/Codex/2026-08-31/new-chat/work/trial-refund-cancellation-20260902-163921.sqlite`

Immediate live backup before scoped migration:

`database/backups/database-before-refund-cancellation-20260902-163944.sqlite`

SHA-256: `4cc6952e2d7c2f7702de36538f87af7f90894ea59922876baf948b248cc54702`

Original financial/catalog rows compared exactly with the backup on both trial and live: 771 products, 710 offers, 23 orders, 23 merchant orders; deliveries, disputes, payments, ledger entries, refunds, destinations and transfer attempts all zero. Foreign-key violations: zero. No actual refund preparation, cancellation, payment or proof upload was performed on working data. Admin payment/cancellation permissions were verified. Withdrawals and automatic settlement remain disabled (the latter defaults to false when unset).

## Verification and limits

`php artisan test --compact`: **145 passed, 1358 assertions**. Blade compilation and refund-transfer route listing passed. Repository-wide `git diff --check` reports whitespace in unrelated existing edits; those files were left untouched.

The cancellation suite covers no-money-movement, history preservation/encryption, idempotent retries, replacement payment, stale forms, permission/password/ownership/attestation/version restrictions, paid/partial-payment rejection, immutable history, fresh destination verification, changed financial state, audit rollback, schema backfill/rollback and active uniqueness.

These are SQLite in-memory feature tests and Blade compilation checks, not browser-driven visual testing, production-engine concurrency tests, bank verification, or a financial reconciliation integration. Current transfer status relies on truthful operator attestation and recorded evidence; simultaneous external actions by different operators cannot be prevented by a local database lock.

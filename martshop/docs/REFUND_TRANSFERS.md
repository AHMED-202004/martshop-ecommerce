# Manual refund transfer recording — 2026-09-02

The application **does not send money**. This workflow prepares a frozen recipient/amount and subsequently records a transfer that an authorized operator attests they performed outside the application.

The existing list now includes [read-only queue filters and per-currency/status summaries](REFUND_TRANSFER_QUEUE.md).

Staff can also run a [per-refund integrity report](REFUND_INTEGRITY_CHECK.md) to check local attempt/ledger/proof consistency without changing financial state.

## Operator workflow

1. Approve the full refund and independently verify the customer destination through the existing workflows.
2. Open `/admin/refund-transfers`. `refunds.pay` is separate from amount review and destination review, and is seeded to the existing admin role. Staff cannot prepare or record their own purchase's refund.
3. On the protected detail screen, confirm the current refund version, destination ID/version and current Mart.ps password. Preparation requires a matching accepted payment, matching original sale and frozen amount breakdown, sufficient sale-specific held balance and a currently verified destination. It creates one active transfer attempt per refund and moves the refund to `processing` without changing balances. Cancelled attempts remain in history.
4. Preparation freezes the encrypted recipient snapshot and amount. Destination services already allow mutations only on requested/approved refunds, so replacement/revocation is blocked during processing and after payment. The parent version increments, invalidating stale forms.
5. Perform the external bank/wallet transfer **once** using the frozen information, then record the exact full amount, unique reference, timestamp, private proof, transfer attestation and current password. Never repeat the external transfer because a page timed out or saving failed; inspect the record and reconcile first.

The subsequent [preparation cancellation workflow](REFUND_TRANSFER_CANCELLATION.md) permits authorized staff to cancel only after independently confirming the transfer was never sent and is not in flight. Paid or uncertain transfers still require external reconciliation; do not manually change database states to unlock a destination.

## Financial integrity

The transfer record stores separate integer minor-unit allocations: merchant net, commission, delivery, and service; these must sum to the customer total. Example: customer refund 120.00 = merchant net 95.00 + commission 5.00 + delivery 20.00 + service 0.00.

Recording appends a merchant held debit and refunded credit for **95.00, not 120.00**. Original sale/payment/order snapshots are not rewritten. The allocations record the components of the outgoing refund; they are not a new full double-entry general ledger or automatic cancellation of courier liabilities. Existing order/payment status remains historical paid, while the suborder refund has its own paid state.

On successful recording, the refund becomes `paid`, the dispute becomes `refunded` with a recorded close reason/time/actor, and normal dispute closure cannot release its funds. Both the scheduled and direct settlement paths exclude refunded disputes, including zero-merchant-net cases.

Transfer operations lock order → delivery → refund (and the related destination/transfer rows). The order lock serializes the aggregate cap: prepared plus recorded refunds for a payment cannot exceed its accepted amount. Financial snapshots and held balances are revalidated at recording time. A changed snapshot, destination or balance fails closed and requires reconciliation if an external transfer has already occurred.

## Idempotency, proof and privacy

- A unique active key makes preparation repeatable without a second active attempt. Cancelled attempts release that key but retain immutable history. Snapshot changes are not accepted on retry.
- Recording requires the exact active attempt ID. A paid retry is accepted only when normalized reference, exact amount, transfer time and proof checksum match. Conflicting retries fail and explicitly warn against repeating the bank transfer.
- References are uppercased, ASCII-validated and unique **among refund transfers**; proof SHA-256 is also unique among refund transfers. These do not deduplicate other systems or the separate withdrawal table.
- Receipt types are JPG/PNG/WebP/PDF, at most 10 MB, validated server-side. Proofs are stored on private local storage with generated filenames, never a public upload URL. No external malware scanning is included.
- Proof download is authorized only for the owner or `refunds.pay` staff, checksum-checked, audited and served as an attachment with octet-stream/nosniff/no-store headers. Staff are instructed to upload only this transfer's proof, excluding unrelated account transactions.
- Recipient data is encrypted and hidden from model serialization; paths and references are hidden from general serialization. General audit entries contain IDs and amounts, not full recipients or receipt contents. Paid transfer records and preparation snapshots cannot be changed/deleted through models.
- File-writing transactions have no automatic retry. A caught failure rolls back transfer/refund/dispute/ledger/audit changes and removes the uncommitted proof. Process crashes and ambiguous database commit/network outcomes still require operational reconciliation and orphan-file recovery; this is not an external bank transaction guarantee.

## Verification and migration

- **136 tests passed, 1245 assertions**, including 11 new transfer tests / 111 assertions.
- Covered: preparation freeze/idempotency/encryption, role/password/self-processing restrictions, stale/unverified destinations, exact amounts and valid timestamps, rejected file types/sizes, duplicate reference/proof, matching/conflicting retries, financial changes after preparation, full rollback and file cleanup after audit failure, immutable paid records, private download ownership, zero-net settlement exclusion and aggregate payment cap.
- New test proof fixtures use PDF bytes, avoiding a dependency on GD for image generation. No global PHP configuration was changed.
- Blade compilation, refund transfer routes and existing route-name uniqueness checks pass. Browser visual testing and production-engine concurrent/crash testing were not performed.
- Migration `2026_09_02_000019_create_refund_transfers.php` creates only the transfer table. Empty-table migration/rollback and foreign-key checks passed on a byte-matched trial copy before live application; rollback refuses a non-empty transfer history.

Immediate pre-migration backup:

`database/backups/database-before-refund-transfers-20260902-163217.sqlite`

SHA-256: `c1bbe98913970257e57d622ff34270e566304306838fb31b30e0466ba844afe2`

Live comparison verified existing rows unchanged: products 771, offers 710, orders 23, merchant suborders 23, deliveries/disputes/payments/ledger entries/refund requests/refund destinations all zero. New refund transfers are zero and foreign-key violations are zero. The existing admin has `refunds.pay`; withdrawals and automatic settlement remain disabled. No actual transfer, refund preparation, proof upload or financial movement was performed on live data during implementation.

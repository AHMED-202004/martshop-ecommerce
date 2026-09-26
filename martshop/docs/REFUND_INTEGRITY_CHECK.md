# Per-refund integrity report — 2026-09-03

Authorized staff can open **فحص اتساق السجل** from the refund transfer list or detail page. The route is `/admin/refund-transfers/{refundRequest}/check`, protected by the existing authenticated session, `refunds.pay` permission, private financial response headers and a 20/minute throttle. The service enforces the same permission for direct callers.

This is a diagnostic report for one refund, not full platform reconciliation, bank confirmation, automatic recovery or a new authorization to send money. It makes no financial, refund, proof, or audit-log writes. No migration, permission seeding or working-database mutation was required.

## Checks implemented

- The immutable amount snapshot contains nonnegative integer minor units and its product/net/commission/delivery/service components agree with the refund total.
- The source sale, delivery and accepted payment have matching order/suborder associations and currencies. Refund amount does not exceed that payment, and sale gross/commission/net/fee components agree with the snapshot.
- The refund status is recognized; relevant dispute and settlement states agree with the requested/approved/processing/paid lifecycle.
- Processing/paid refunds have exactly one non-cancelled attempt; other states have none. Active keys, cancellation state and partial receipt metadata are checked independently, so a broken active association does not hide an attempt from the report.
- Each attempt's amount, component amounts, currency and payment ID match the refund/source snapshot. Cancelled attempts with receipt traces are flagged, not silently treated as safe cancellations.
- Paid attempts require receipt metadata and a proof file with matching size and stored SHA-256. Only generated proof paths under that specific attempt's private directory are inspected. Directory traversal, resolved-path escapes and files exceeding the upload-size metadata limit are rejected. The content of the receipt is not interpreted or displayed.
- A paid refund must have the expected held debit and refunded credit for merchant net, including zero-net refunds. Keys, direction, bucket, signed amount/net, currency, merchant, order/suborder and payment linkage are compared. Missing/extra refund entries for the same suborder are flagged; nonpaid requests must have no refund entries.
- The sum of non-cancelled attempts using the payment must not exceed the accepted payment amount.

## Privacy and operational boundaries

Reports contain only the refund reference, check time, checked record counts, fixed diagnostic messages/codes and relevant attempt IDs. They do not show account identifiers, recipient names, payment references, cancellation notes, proof paths or file contents. Raw database reads avoid decrypting recipients or crashing on an unknown refund status; malformed amount snapshots produce a diagnostic finding.

The database reads run within one read transaction. This is not a distributed snapshot of the bank, filesystem and database; files and records can change during or after review. The report is not stored as a signed finance closing. It does not examine all merchant bucket balances, withdrawal/courier liabilities, external transaction settlement, document authenticity, recipient ownership or every possible database invariant. It performs no repair and does not relax existing action validation.

The report is per-refund and inspects its attempt history; it is not a global background scan or notification monitor. On a healthy normal lifecycle at most one paid proof is read. Production-engine concurrent/crash/load testing and browser-driven visual testing remain release gates.

## Verification

`php artisan test --compact`: **158 passed, 1511 assertions**.

Seven added feature tests use SQLite `:memory:` and fake private proof storage. Coverage includes the healthy approved/prepared/cancelled/reprepared/paid lifecycle; zero merchant net; no financial/audit writes; private-field non-disclosure and headers; missing/changed/invalid-path/incomplete proof; incorrect/missing/extra ledger entries; broken attempt states/keys/amounts/payment capacity; malformed snapshots and unknown statuses; rejected source payments and changed sale components; and HTTP/service permission enforcement.

Blade compilation, service PHP syntax and the new route listing passed. Read-only working database observation: 771 products, 710 offers, 23 orders and 23 merchant orders; refund requests, refund transfers and ledger entries remain zero. Foreign-key violations: zero. Withdrawals and automatic settlement remain disabled. No live refund was created merely to demonstrate the report.

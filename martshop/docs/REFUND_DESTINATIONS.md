# Verified refund recipients — 2026-09-02

## Privacy hardening checkpoint — 2026-09-09

- Customer ownership checks now use constrained existence queries instead of hydrating the full dispute, delivery and order graph before authorization.
- Customer and admin destination lists select only the identifiers, statuses, versions and timestamps they render. The private detail page loads only the relationship fields needed to establish the owner and current review state.
- The staff destination queue excludes records submitted by the current reviewer. The service-level self-review prohibition remains the authoritative mutation guard.
- Refund review lists now constrain refund, dispute, delivery and active-destination columns to the fields rendered by the page.
- Refund amount snapshots and customer/reviewer reasons are hidden from default model serialization; protected Blade views continue to access them explicitly.
- Focused refund destination/review coverage passed: **21 tests, 252 assertions**. The complete suite passed: **229 tests, 2571 assertions**. No migration or working-database mutation was performed.

This batch adds customer recipient registration and **manual ownership verification**, not outgoing transfer execution. It reuses the existing refund/dispute workflow without changing merchant payout accounts or inferring a recipient from the platform's payment-receiving account.

## Workflow

- Customers reach the destination page from the refund shown in `/order-history`. A destination belongs to one refund, not an unrestricted reusable customer payout account.
- The owner may submit a bank account or mobile wallet while the refund is requested/approved, the dispute is open and the delivery is unreleased. Provider names are entered, not hard-coded. Identifier syntax accepts Latin letters/digits, optional leading `+`, spaces and hyphens; normalization removes spaces/hyphens and uppercases letters. This is syntax validation, **not** bank/IBAN or ownership validation.
- Current Mart.ps password and a fresh refund version are required for submission. No banking password, wallet password or OTP is requested. The name, provider and normalized identifier form an encrypted immutable recipient snapshot.
- Pending and verified destinations share a unique active key derived from the refund ID. Identical retries return the existing destination; a different destination cannot silently overwrite it.
- `/admin/refund-destinations` requires the separate `refund-destinations.review` permission. It is seeded to the existing admin role only; refund-amount reviewers do not automatically receive access through `refunds.review`.
- Only the customer owner and authorized destination staff may open full details, and successful detail access is audited. Lists show only the last four identifier characters. Protected pages have no third-party assets/chat/scripts and use no-store, no-referrer, anti-framing and restrictive CSP headers.
- Staff verification/rejection requires a decision, protected notes, current password, destination version, and (for verification) an explicit independent-ownership-check attestation. Staff cannot review their own purchase. There is no automated external verification provider or evidence upload in this batch; staff must perform and describe the check themselves. Notes are visible to the owner and authorized staff only.
- Rejection retains the history and frees the active slot. An owner can revoke a pending/verified destination with password confirmation. Revocation preserves the original recipient and review record. A replacement starts pending and needs fresh verification, even if its account matches a historical entry.
- Submission, review and revocation increment the parent refund version, making stale amount-review pages fail safely. All changes and their audit entry run in the same database transaction, locking delivery → refund → destination. No request here moves funds, closes a dispute, or marks a refund paid.

## Privacy and future execution contract

Recipient snapshots and review notes are encrypted using the existing application key and hidden from default model serialization. Preserve that key and protect backups. Sensitive input fields are excluded from validation-error session flash data. General audit entries contain IDs/statuses, not recipient names, full identifiers or private verification notes.

Historical records are immutable as to their recipient, refund association, submitter and last four characters; deletion is prohibited at model level. The unique active-key constraint also prevents multiple current destinations. These protections do not replace database access controls for privileged direct SQL.

The subsequent [manual transfer-recording batch](REFUND_TRANSFERS.md) now prepares a frozen recipient version and records the external transfer/proof and ledger movements. Preparation blocks destination changes while the refund is processing. Never pay a historical verified destination after it was revoked. A rejected/closed refund is not payable even if its destination record retains an old pending/verified status.

No partial refunds, third-party recipient approvals, destination auto-approval, actual bank lookup, reversal of platform/delivery fees, or inventory returns were added. Approved refunds remain held pending execution work.

## Verification and migration

- Full suite: **125 tests passed, 1134 assertions**, including 9 destination tests / 105 assertions.
- Covered: encryption and masked lists, owner/staff isolation, independent permission, reauthentication, malformed identifiers, snapshot immutability, duplicate submission/verification, self-review rejection, stale form rejection, revocation/replacement, rejected history, protected view auditing, parent-version invalidation and submission audit rollback.
- Blade compilation, refund route listing, and existing route-name uniqueness test pass. Browser visual testing and multi-connection production concurrency testing were not performed.
- Migration `2026_09_02_000018_create_refund_destinations.php` creates only `refund_destinations`; its rollback refuses to erase non-empty history (tested).
- Trial migration, seeding, empty rollback, and foreign-key checks passed on a byte-matched copy before live application. Immediate backup:

`database/backups/database-before-refund-destinations-20260902-161529.sqlite`

SHA-256: `9c7afba0ea8c8f00845fa21b8a30f8abbe67ef65578e7e04e52efbd87a62b0db`

Live comparison against the backup found all rows unchanged in products (771), offers (710), orders (23), suborders (23), deliveries (0), disputes (0), payments (0), ledger entries (0), and refund requests (0). Destinations remain empty and foreign-key violations are zero. The existing admin has the new permission; automatic settlement and withdrawals remain disabled. No real customer recipient was submitted or approved by this implementation.

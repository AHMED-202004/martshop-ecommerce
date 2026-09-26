# Financial proof storage security

## Stored-file integrity

- Payment, withdrawal and refund-transfer services calculate size and SHA-256 from the private file after it is written.
- The stored digest must match the uploaded source before a financial record or ledger transition can commit.
- A storage-integrity failure rolls back the database transaction and removes the newly written file.

## Download confinement

- Payment, delivery, withdrawal and refund-transfer downloads share one fail-closed verifier for local-path confinement, recorded size and SHA-256, while each controller retains its exact directory and generated-filename rule.
- Refund-transfer proof downloads now require the exact transfer-specific directory and generated UUID filename format.
- Real paths must remain inside that directory.
- Recorded size must be positive, within the upload limit and equal the stored file size.
- SHA-256 metadata and stored bytes must match before an access audit is written.
- Tampered paths, sizes and hashes fail closed without exposing file content.

## Authorization and immutability

- Withdrawal review and payment services repeat the `withdrawals.approve` permission check before any idempotent or state handling.
- Payment review and accepted-payment ledger creation repeat the `payments.verify` permission check.
- Dispute and withdrawal ledger bucket moves independently verify the actor, aggregate ownership and required current state before idempotent handling.
- Payment and withdrawal proof records cannot be updated or deleted through their models.
- Internal proof paths, original names, MIME types, sizes and hashes are hidden from serialization for payment, withdrawal and delivery proofs.

## Payment review minimization

- The pending-payment list selects only the payment, order, customer display name and method name fields rendered by the table.
- It no longer loads proof records or unused customer contact fields.
- The review detail reloads a minimized payment and only the exact order, customer, method, proof identifier and reviewer fields it renders.
- Provider references, sender identity/account values, idempotency/open keys, internal snapshots and review notes are hidden from payment serialization.
- Payment method account instructions and identifiers are hidden from model serialization.

## Withdrawal page minimization

- Merchant withdrawal pages reload only the merchant identifier/status, masked payout-method fields and withdrawal columns rendered by the page.
- The merchant history no longer loads full payout methods or full proof metadata.
- Admin withdrawal review loads only the legal merchant name, exact payout destination fields required for independent verification, current request state and proof identifier.
- Unused merchant users and reviewers are no longer eager-loaded.
- Withdrawal destination snapshots, transaction references, idempotency keys and review notes are hidden from model serialization; payout account names and review notes are hidden as well.

## Verification

- Latest focused payment/delivery/withdrawal/refund proof coverage: **70 tests, 887 assertions**. The complete suite passed: **337 tests, 4630 assertions**.
- A read-only working-database scan verified all five payment proofs and two delivery proofs with zero invalid files; no withdrawal or refund-transfer proof exists yet.
- Focused payment, withdrawal and refund-transfer coverage: **59 tests, 719 assertions**.
- Payment authorization plus payment/withdrawal minimization coverage passed; complete suite: **228 tests, 2566 assertions**.
- Blade compilation passed.
- All **144 routes** retain unique names and method/URI signatures.
- No migration was added or applied, and no working/live database values were changed in this checkpoint.

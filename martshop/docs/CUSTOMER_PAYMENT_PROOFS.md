# Customer payment pages and proofs — 2026-09-08

The manual-transfer form and submission response now use the private-account middleware. Approved payment instructions, account identifiers, submitted references and customer sender details receive `no-store, private`, `no-referrer`, frame-denial, MIME-sniffing protection and the storefront CSP. A different customer is denied before payment-method details render.

Validation failures no longer flash transfer references, sender names/accounts, amounts, transfer times or idempotency keys into session storage. Proof file fields were never flashed. The existing owner authorization, active-method recheck, order row lock, idempotency key, unique provider reference, exact server amount snapshot, private storage and transactional cleanup remain unchanged.

## Proof download integrity

Payment proofs are served only from `local` storage and from the exact generated `payment-proofs/{payment_id}/{uuid}.{allowed-extension}` directory. Before any audit event or response, the controller verifies:

- the normalized path remains inside that payment's private directory;
- the file exists and is a regular file;
- stored and actual sizes match;
- the stored SHA-256 has a valid shape and matches the file contents.

Missing, traversing, malformed or changed proofs return 404 without a view audit. Valid proofs are downloaded as `application/octet-stream` with a generated non-user filename, attachment disposition, `nosniff`, `no-store`, no-referrer and frame denial. Owner or `payments.verify` authorization remains mandatory, and successful reads append `payment.proof_viewed`.

## Interface and verification

The payment page displays the stored currency instead of assuming a symbol, documents the 8 MiB upload limit, avoids narrow-screen overflow, and makes primary mobile actions full width.

Five added tests cover private form headers, cross-customer denial before method disclosure, non-flashing validation, valid checksum-verified download/audit, path/size/hash tampering, and unauthorized proof access. Focused payment/history suite: **18 passed, 140 assertions**. Full suite: **190 passed, 1848 assertions**. Blade compilation and PHP syntax checks passed. Test proofs use fake local storage and SQLite `:memory:`; no live payment, proof, order, audit or financial state was changed.

The later administrator-side decision safeguards and their newer full-suite checkpoint are documented in [ADMIN_PAYMENT_REVIEW.md](ADMIN_PAYMENT_REVIEW.md).

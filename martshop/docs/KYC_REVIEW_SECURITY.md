# Merchant KYC review security — 2026-09-08

Administrator merchant-verification pages and private merchant-document downloads now use the strict private-response middleware. Responses are non-cacheable, send no referrer, deny framing and MIME sniffing, and use the restricted Content Security Policy. Administrator review pages use the private layout without third-party page assets.

## Reauthentication and reviewer separation

Approving, rejecting, suspending or requesting changes for a merchant now requires the reviewer's current Mart.ps password. Accepting or rejecting an identity document has the same requirement. The password and review notes remain excluded from flashed validation input.

A staff account cannot verify its own merchant profile or review its own merchant documents. This separation is enforced by both the policies and `MerchantVerificationService`, so direct internal service calls cannot bypass it. Existing transactional row locks, state-transition validation and audit records remain in place.

Mutation routes are rate limited. Merchant decisions allow ten attempts per minute per applicable Laravel throttle key; document-review decisions allow twenty.

## Data minimization

The merchant-review queue now selects only its visible list fields and relationship identifiers. It does not load encrypted identity numbers, identity hashes, phone numbers, birth dates, private addresses or business details for every queue row. Those details are loaded only on an individually authorized review page.

Private documents remain on the configured private `local` disk and continue to require document-view authorization. New uploads store a SHA-256 digest and derive both digest and size from the file after it is written, rather than trusting client upload metadata. If database persistence then fails, the newly written file is removed.

Before download, the controller requires the private `local` disk, the expected merchant-specific UUID path, a real path confined beneath that merchant's document directory, an exact stored-size match and, when present, an exact SHA-256 match. Failed checks return not found and do not create a successful-view audit event. Successful downloads use a generated filename and generic binary content type, and record `merchant.document_viewed` without exposing the storage path.

Migration `2026_09_08_000021_add_integrity_hash_to_merchant_documents.php` adds a nullable digest for compatibility with existing rows. It was applied to the working database on 2026-09-13 after copy-based migration/rollback testing. The three historical documents were verified from private storage and backfilled by the audited maintenance command; no document remains without a digest.

## Verification

Focused KYC, identity-foundation, catalog-suspension and dashboard coverage passed: **26 tests, 257 assertions**. A subsequent focused document-integrity/schema run passed: **12 tests, 74 assertions**. The complete suite passed: **208 tests, 2170 assertions**. Blade compilation and PHP syntax checks passed, and all **143 routes** have unique names and method/URI signatures.

Tests use SQLite `:memory:` and fake private storage. No live merchant status, identity document, offer or audit record was created or changed. Production operation still requires HTTPS/session configuration, controlled reviewer accounts, document-access monitoring and a documented KYC retention policy.

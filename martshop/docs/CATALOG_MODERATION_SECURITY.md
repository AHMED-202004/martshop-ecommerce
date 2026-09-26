# Catalog moderation security

## Implemented controls

- The catalog moderation page and its mutations use private, no-store responses with no-referrer, anti-framing, MIME-sniffing and restrictive content-security headers.
- Every product, offer and product-change decision requires the reviewing account's current password.
- All three mutation routes are rate limited.
- Form requests authorize the exact routed record through its policy instead of checking only a broad permission.
- `CatalogModerationService` and the product-change review service repeat authorization after loading and locking the current database record. Calling a service directly cannot bypass policy checks.
- A reviewer account linked to a merchant cannot review that merchant's product, offer or product-change request. Product review also excludes products for which the reviewer merchant has an offer because product decisions cascade to related offers.
- Conflicted records are omitted from the review queues, while direct route and service attempts fail before mutation.
- Queue queries select only fields rendered by the review page and no longer load merchant email addresses or unused internal metadata.
- Moderation audit snapshots keep decision state and concurrency fields but do not duplicate review notes, price or stock. The decision reason remains in the dedicated protected audit reason field.
- Validation failures do not flash the current password or review reason back into the session.

## Verification

- Focused catalog, product-change and authorization coverage: **19 tests, 182 assertions**.
- Complete suite: **214 tests, 2266 assertions**.
- Blade compilation passed.
- All **143 routes** retain unique names and method/URI signatures.

Coverage includes private response headers, minimized queue attributes, wrong-password rejection, secret-free old input, unauthorized direct service calls, and self-review attempts through both HTTP routes and service calls.

No working/live database values were changed, and this batch adds no database migration.

# Delivery-proof security — 2026-09-09

## Service-level upload validation — 2026-09-11

- Delivery confirmation repeats the four-digit PIN, non-negative lock version, 1000-character note, JPEG/PNG proof type and 10MB proof limit inside `DeliveryConfirmationService` after worker authorization and before any file write.
- The HTTP Form Request reuses the service's rule set, preventing controller/service validation drift. Direct service calls with unsupported proof content fail without creating proof, event, audit or delivery-state changes.
- Latest focused catalogue/delivery/proof coverage passed: **46 tests, 532 assertions**. The complete suite passed: **274 tests, 2888 assertions**.

Delivery-proof downloads now use the strict private-financial response middleware. Responses are non-cacheable, send no referrer, deny framing and MIME sniffing, and use the restricted Content Security Policy.

## Stored-file integrity

Delivery confirmation derives the proof size and SHA-256 digest from the private file after it is stored instead of trusting client upload metadata. The same digest is used in the proof row and delivery event. Existing transaction rollback cleanup remains responsible for removing a newly stored proof when confirmation fails.

Before download, the controller requires the private `local` disk, the expected delivery-specific ULID image path, a real path confined beneath that delivery's proof directory, an exact stored-size match and an exact SHA-256 match. Failed checks return not found and do not create a successful-view audit event.

Successful downloads use a generated filename and generic binary content type instead of reflecting the uploader's original filename or serving the image inline. Authorized views continue to create `delivery.proof_viewed` audit events without exposing storage paths.

## Verification

Focused delivery and schema coverage passed: **19 tests, 215 assertions**. After the related delivery-page privacy pass, the complete suite passed: **209 tests, 2199 assertions**. PHP syntax checks passed, and all **143 routes** retain unique names and method/URI signatures. Page-level privacy and query minimization are documented separately in [DELIVERY_PAGE_PRIVACY.md](DELIVERY_PAGE_PRIVACY.md).

Tests use SQLite `:memory:` and fake private storage. No live delivery, proof, ledger entry or audit row was created or changed. Production operation still requires HTTPS/session configuration, private-storage permissions, backup/restore testing and controlled proof-retention procedures.

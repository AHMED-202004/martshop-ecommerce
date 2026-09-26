# Product change image security

## Implemented controls

- New proposed product images are stored only on the private `local` disk under a merchant-specific directory and a generated UUID filename.
- The size and SHA-256 digest are calculated from the stored file, not from client-supplied upload metadata, and saved with the change request.
- Image access remains policy-protected for the owning merchant or a user with `products.moderate`.
- Downloads accept only the private disk, constrained image paths and supported extensions. Real paths must remain inside the expected storage directory.
- Current-format images require complete size and digest metadata. Both values are checked before a file is returned or copied to the public product catalog.
- Responses use a generated filename, a generic binary content type, `nosniff`, and private no-store headers. Successful views are audit logged.
- Replacing an image during a valid resubmission deletes the previous verified private file after the database transaction succeeds.
- Audit snapshots record only whether an image exists and its size; private storage paths and digests are excluded.

## Legacy compatibility

Legacy framework-generated paths directly below `product-change-requests/` remain readable only when they match the exact historical random-name pattern and resolve inside that directory. If integrity metadata exists, it is enforced. Legacy rows with both integrity fields null remain compatible, but do not gain digest verification automatically.

Before production release, perform a controlled backfill of size and SHA-256 values for legitimate legacy files. Do not populate those values from untrusted request data.

## Database deployment

Migration `2026_09_09_000022_add_integrity_metadata_to_product_change_requests.php` adds nullable `proposed_image_size` and `proposed_image_sha256` columns. It was applied to the working database on 2026-09-13 after a verified backup and copy-based migration/rollback test; no stored change-request image currently lacks integrity metadata.

## Verification

- Focused product-change tests: **5 tests, 72 assertions**.
- Catalog and schema integration tests: **18 tests, 168 assertions**.
- Complete suite: **211 tests, 2229 assertions**.
- Blade compilation passed.
- All **143 routes** retain unique names and method/URI signatures.

The coverage includes authorized and unauthorized access, private download headers, stored-file size and digest calculation, path/disk/size/hash tampering, refusal to approve a corrupted image, audit logging, and cleanup of a replaced image.

# Merchant catalog security

## Product-image storage boundary — 2026-09-11

- Private product-submission storage independently checks detected file content type and the actual byte size before writing. Only JPEG, PNG and WEBP up to 5MB are accepted, regardless of the client filename or a caller bypassing the HTTP Form Request.
- Stored filenames remain server-generated, and rejected disguised or oversized files leave no private-storage artifact.
- Latest focused catalogue/delivery/proof coverage passed: **46 tests, 532 assertions**. The complete suite passed: **274 tests, 2888 assertions**.

## Service authorization

- Catalog creation, offer updates, pause/resume, product resubmission and product-change submission now receive the authenticated actor explicitly.
- Every service verifies that the actor owns the supplied merchant record and repeats ownership/status checks after locking current database rows.
- Offer lifecycle methods also enforce their exact `update`, `pause` or `resume` policy inside the service. Direct service calls cannot impersonate another merchant.
- Authorization is checked before image storage, so a rejected spoofing attempt does not create a temporary private file.

## Pending product images

- Images for newly submitted or corrected products are stored on the private `local` disk under a merchant-specific directory and generated UUID filename.
- Size and SHA-256 are calculated from the stored bytes and recorded on the product.
- The private preview route is available only to the owning merchant or a non-conflicted catalog moderator. It validates disk, path, real-path confinement, size and digest before returning an inline image.
- Pending images use private no-store responses, `nosniff`, generated filenames and audit logging.
- A pending image is copied to the public product directory only as part of a successful approval transaction. Copy failures or integrity failures prevent approval.
- After approval commits, the private source is deleted. Replacing an image during correction deletes the previous verified private source after the database transaction succeeds.
- Existing legacy products that already reference public images remain compatible; this change controls newly submitted private images.

## Page privacy and minimization

- Merchant catalog pages and mutations receive private no-store, no-referrer, anti-framing, MIME-sniffing and content-security headers.
- The catalog index selects only the merchant, offer, product and open-change fields rendered by the page. Identity details and unused offer metadata are not loaded into the view.
- Creation lists select only the location identifiers and names required by the form.
- Merchant category selectors now include only ancestor-aware publicly visible categories.
- New-product submission, correction and product-change requests validate category visibility at the request boundary and repeat it inside the transactional service. Product-change approval checks it again, so direct service calls and stale pending requests cannot restore a hidden branch.
- Existing-product selectors and offer submission services exclude products assigned only to hidden category branches. Uncategorized legacy products remain compatible until their catalogue migration is completed.

## Database deployment

Migration `2026_09_09_000023_add_submission_image_integrity_to_products.php` adds the private submission disk/path, size and SHA-256 columns. It was applied to the working database on 2026-09-13 after a verified backup and copy-based migration/rollback test.

## Verification

- Focused catalog, product-change, authorization and schema coverage: **23 tests, 230 assertions**.
- Complete suite: **216 tests, 2312 assertions**.
- Blade compilation passed.
- All **144 routes** retain unique names and method/URI signatures.

Coverage includes private image publication, access control, integrity/path/disk/content tampering, replacement cleanup, merchant-page response privacy, minimized view data and direct service impersonation attempts.

Latest incremental product/category/merchant/API coverage passed **39 tests / 464 assertions**; the complete suite passed **264 tests / 2801 assertions**.

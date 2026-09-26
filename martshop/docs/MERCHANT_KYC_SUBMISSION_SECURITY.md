# Merchant KYC submission security

## Reauthentication and response privacy

- Creating or updating the merchant identity profile, uploading an identity document and submitting the profile for review require the current account password.
- Failed validation never flashes the password, legal name, identity number, phone, birth date, address or business type into session old input.
- Profile reads and all related mutations use private, no-store, no-referrer, anti-framing, MIME-sniffing and content-security response headers.
- Mutation routes are rate-limited to five attempts per minute.

## Service authorization

- `MerchantRegistrationService` rechecks the locked current merchant record before updating or submitting it.
- A profile in a non-editable state cannot be changed by calling the service directly.
- `MerchantDocumentStorage` reloads the merchant and verifies that the actor owns an editable profile before writing a private file.
- Direct cross-account submission and document-upload attempts fail before changing database or storage state.

## Data minimization

- The profile page selects only the merchant, document and location columns it renders.
- Document disk paths, hashes, original filenames, review notes and other internal metadata are hidden from serialization.
- Merchant identity number, phone, birth date, address and review notes are hidden from serialization while remaining available to the authorized profile view.
- Audit snapshots no longer persist phone numbers or birth dates. Identity numbers and exact addresses were already excluded.

## Verification

- Focused merchant-verification and identity-foundation coverage: **11 tests, 112 assertions**.
- Complete suite: **218 tests, 2393 assertions**.
- Blade compilation passed.
- All **144 routes** retain unique names and method/URI signatures.
- No migration was added or applied, and no working/live database values were changed in this checkpoint.

The SQLite feature suite does not replace production checks for simultaneous uploads, web-server upload limits, antivirus/content scanning, object-storage permissions, HTTPS or session-cookie configuration.

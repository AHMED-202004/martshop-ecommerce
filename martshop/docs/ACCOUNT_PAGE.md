# Signed-in account page — 2026-09-04

## Account and registration hardening — 2026-09-10

- Saving delivery/contact details now requires the current Mart.ps password. The controller excludes that password from profile updates, locks the user row, and verifies the password again inside the same transaction as the update and audit event.
- User models now hide identity, contact, address and birth fields from default array/JSON serialization. Protected Blade pages can still read explicitly requested model properties.
- Profile, registration and login identifiers are excluded from validation-error session flashing. A failed login no longer explicitly puts the submitted identifier back into the session.
- Registration input is normalized in a dedicated form request. Passwords require 12–72 characters with letters and numbers; phone syntax requires at least seven digits; alternate and primary contact numbers must differ; control characters are rejected from address/name fields; and the assembled birth date must be a real calendar date.
- Email logins are lowercased, email/phone login identifiers are checked for uniqueness, placeholder recovery addresses are generated only for validated phone registrations, and user creation plus customer-role assignment run in one database transaction.
- Login trims identifiers and lowercases email addresses before authentication. The redundant second credential lookup was removed while preserving the generic failure response.
- Focused account/address/recovery/settings coverage passed: **33 tests, 311 assertions**. The complete suite passed: **235 tests, 2631 assertions**. No migration or working-database mutation was performed.

## Login-phone database integrity — 2026-09-10

- Migration `2026_09_10_000024_add_unique_login_phone_to_users.php` adds a named unique constraint to nullable `users.phone`, closing the race between application uniqueness validation and account creation.
- The migration first checks for existing duplicate non-null phone values and fails without changing the schema if any exist. The application converts a database uniqueness race into the same generic registration validation error used by the form.
- A preflight found 4 users and **0 duplicate non-null phone groups**. The unique-phone migration was applied to the working database on 2026-09-13 after a verified backup and copy-based migration test.
- Focused schema/registration/account coverage passed: **22 tests, 178 assertions**. The complete in-memory suite passed: **237 tests, 2633 assertions**.

The authenticated `/my-account` route now has a dedicated `profile` action and view. It no longer reuses the guest login/registration action, so query parameters such as `?tab=register` cannot make a signed-in user see a registration form.

## Page contents

- The signed-in user's own name, real email (when available), phone numbers, delivery address, gender and date of birth.
- Safe placeholders for incomplete legacy profiles. Synthetic `@noemail.local` addresses used by phone-only registrations are not displayed as real email addresses.
- Links to order history, address/contact editing, merchant verification and—only when current permissions allow it—the administration dashboard.
- A direct **حسابي** link in the authenticated storefront header, marked with `aria-current="page"` on the account page.
- A two-column layout that collapses to one column below 700 CSS pixels, with minimum 44-pixel primary actions.
- An authenticated password-change form requiring the current password and a confirmed replacement of 12–72 characters containing letters and numbers. The replacement must differ from the current password.

All values are escaped by Blade. The controller passes only the authenticated request user; the page does not query or expose another user's profile.

## Privacy response

The authenticated page uses a dedicated response middleware with `no-store, private`, `no-referrer`, frame denial, MIME sniffing protection and a CSP constrained to the existing storefront's self-hosted assets plus its current font/style providers. Guests are redirected by authentication middleware before the profile action runs.

Successful password changes lock the account row, rotate the remember token, invalidate outstanding recovery tokens, remove the account's other database sessions, and write a secret-free audit event in one transaction. The current request regenerates its session and remains signed in. An audit failure rolls the password, token and session changes back together. Submissions are limited to five attempts per minute.

## Address and contact editing

The authenticated `/address` page now shares the account privacy middleware and returns explicitly to `/my-account` after saving or cancelling. Inputs are trimmed, text fields reject control characters, and phone fields accept common phone punctuation while requiring at least seven digits. The optional number must differ from the main contact number.

The update changes only the authenticated user's delivery/contact fields. The login phone remains separate and unchanged; the account page labels **رقم تسجيل الدخول** and **جوال التواصل** independently. Updating is transactional with a secret-free `account.delivery_details_updated` audit event. Addresses and phone values are deliberately excluded from that event, and an audit failure rolls back the profile change.

## Verification

Feature tests cover guest redirection, preservation of the guest login form, signed-in profile rendering, own-user isolation, registration-tab query resistance, private response headers, escaped profile values, incomplete-profile placeholders and synthetic-email suppression. Password-change coverage includes wrong/current-password validation, secret-free input and audit data, role preservation, remember-token rotation, recovery-token invalidation, other-session revocation, unrelated-session preservation, transactional rollback, authentication and rate limiting.

Address tests additionally cover authentication, own-user isolation, output escaping, private headers, normalization, login-phone preservation, invalid-number rejection, secret-free auditing and transactional rollback.

`phpunit --do-not-cache-result`: **180 passed, 1766 assertions**. Blade compilation and PHP syntax checks passed. The authenticated account page was observed in the browser before these additions. Refreshing after the local browser session expired correctly redirected to login; no password or address form was populated or submitted during browser verification.

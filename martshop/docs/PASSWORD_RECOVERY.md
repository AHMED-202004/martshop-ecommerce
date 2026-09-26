# Password recovery — 2026-09-02

The login page now links to `/forgot-password`. Laravel's existing `users` password broker and `password_reset_tokens` table are reused; no migration, package, replacement authentication system, or role change is needed.

## Implemented safeguards

- Arabic request/reset pages; replacement passwords require at least 12 characters including letters and numbers, with confirmation.
- Hashed reset tokens, configured 60-minute expiry, one-time consumption, and replacement of previous tokens. Invalid, expired, wrong-account and reused tokens cannot change a password.
- Requests are limited per IP and hashed email key. Broker throttling additionally prevents rapid replacement emails. Known/unknown/throttled accounts receive the same generic delivery response.
- Email links use `PASSWORD_RESET_URL`, never the submitted Host header. Only an explicit real mail transport is accepted; log, array and failover-to-log cannot receive reset messages. Outside local/testing, HTTPS is required.
- The recovery pages are standalone and contain no chat, external images, scripts or fonts. They use no-referrer/no-store headers and restrictive CSP/frame protections.
- Reset validation never flashes tokens or passwords. The existing login/registration error paths were also corrected to avoid flashing passwords with `withInput()`.
- Password update, remember-token rotation, reset-token consumption and audit logging share a transaction and an account row lock. No automatic login follows a successful reset. Database-backed account sessions are removed; unrelated users' sessions and all roles/permissions remain unchanged.
- Audit logs contain neither passwords nor recovery tokens. Delivery failures record only the exception class, not potentially sensitive transport messages.

The signed-in account page also supports changing a known password. It applies the same 12–72 character letter-and-number policy, verifies the current password again under the account row lock, invalidates outstanding recovery tokens, rotates the remember token, preserves the current regenerated session, revokes the account's other database sessions, and records `account.password_changed`. This route is authenticated and limited to five attempts per minute.

## Mail setup required

Observed working environment uses `MAIL_MAILER=log` and has no SMTP credentials. Recovery email remains disabled (`PASSWORD_RESET_MAIL_ENABLED=false`). The UI clearly reports that delivery is unavailable rather than claiming a message was sent.

Before enabling: configure a real outgoing mail service, a verified sender, a stable trusted absolute `PASSWORD_RESET_URL`, and test actual receipt. The existing protocol-relative temporary tunnel `APP_URL` is not accepted for recovery email. Do not place credentials or live recovery URLs in this document or Git. Phone-only placeholder accounts do not receive recovery mail.

The owner explicitly approved issuing a temporary local recovery link for their specified admin account. This uses the same broker and reset page; it does not choose or change their password. The new link supersedes any previous link. Do not publish it or store it in documentation. Local access requires the project's development server to be running. The attempt to start that server automatically was denied by the tool environment, so the owner was asked to run `php artisan serve` in the project folder.

## Verification and boundaries

- **116 tests passed, 1029 assertions**, including 8 password-recovery tests / 78 assertions.
- Blade compilation and password route listing pass. Browser-level validation was not completed because the local server was stopped.
- Tests cover delivery privacy, trusted link origin, disabled mail, hashed tokens, expiry/replacement/replay, roles retained, session revocation, secret-free audit/input, rate limits and rollback on audit failure.
- Current session driver is database on the default database connection. Migrating to another session store/connection requires revisiting atomic session revocation. Concurrent in-flight requests and production-engine concurrency/crash behavior have not been tested.
- Actual email deliverability, deployment access-log redaction for recovery URLs, TLS and production settings require deployment verification. This is not an end-to-end production security audit.

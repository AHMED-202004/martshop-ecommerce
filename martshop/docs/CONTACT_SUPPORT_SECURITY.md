# Contact support and admin inbox

## Public support flow

- The support widget no longer simulates sending a message only in the browser. It links to the persisted contact form.
- Public footer contact details come from the admin-managed phone, WhatsApp, email and support-hours settings and are escaped when rendered.
- Unsupported Visa and MasterCard advertising was removed; the footer describes the currently implemented manual-transfer flow.
- Contact submissions remain private, rate-limited, server-validated and excluded from flashed sensitive input.
- Contact, reference and message values are trimmed before storage. Contact must be a valid email or a bounded phone number with at least seven digits; control characters are rejected and browser length hints match server limits.

## Admin inbox

- Customer messages are available read-only at `admin/contact-messages`.
- Access requires the dedicated `contact-messages.view` permission and uses private no-store security headers.
- The paginated inbox loads only message ID, topic, customer reference and timestamp; it neither queries nor renders the contact value, order reference or message body.
- Private content is loaded only on the separate detail page, escaped when rendered, and each authorized opening appends a `contact-message.viewed` audit record. Authorization runs before the private record query.
- Neither page provides deletion or mutation actions.
- The dashboard and collapsed admin navigation expose the inbox only to permitted staff and show its current message count.
- `AuthorizationSeeder` defines the new permission and attaches it to the system admin role. Run the normal authorization seeding step during deployment; it was not run against the working database during this audit.

## Performance and verification

- Marketplace settings are loaded in one bounded query per service instance instead of one query per footer value.
- Latest focused contact/admin/audit coverage: **19 tests, 248 assertions**.
- Complete suite: **282 tests, 3040 assertions**.
- Blade compilation and JavaScript syntax passed.
- All **146 routes** retain unique names and method/URI signatures.
- No migration was added or applied, no seeder was run on the working database, and no working/live database values were changed.

# Delivery-page privacy — 2026-09-09

The administrator assignment page and delivery worker's own-task page contain exact pickup/delivery addresses, names and phone numbers. Their GET and mutation routes now use the strict private-financial response middleware. Responses are non-cacheable, send no referrer, deny framing and MIME sniffing, and use the restricted Content Security Policy.

Both pages now use the private layout without storefront chat, analytics or third-party page assets. The notices identify address and contact data as task-scoped operational information that must not be shared or reused.

## Query minimization

The administrator queue no longer loads the customer user relation or unused origin-location relation. It fetches only the order snapshot fields used for assignment, the already-minimized merchant contact fields, the assigned worker and only proof identifiers needed to build the protected link.

The worker queue no longer loads event actors or full proof rows. It fetches only merchant-order item display fields, event type/timestamp fields and proof identifiers. Delivery snapshots remain available because exact origin and destination details are required to perform the assigned task.

No assignment, state-transition, PIN, authorization or rate-limit behavior changed in this privacy pass. Later defense-in-depth checks inside the delivery services are documented in [DELIVERY_SERVICE_AUTHORIZATION.md](DELIVERY_SERVICE_AUTHORIZATION.md).

## Verification

Focused delivery and dashboard coverage passed: **26 tests, 359 assertions**. After the service-authorization pass, the complete suite passed: **210 tests, 2204 assertions**. Blade compilation and PHP syntax checks passed, and all **143 routes** retain unique names and method/URI signatures.

Tests use SQLite `:memory:` and fake private storage. No live delivery, worker assignment, customer address, proof or audit row was created or changed. Production deployment still requires HTTPS/session configuration, least-privilege worker accounts, device security and an operational policy for contact-data retention.

# Delivery service authorization — 2026-09-09

Delivery HTTP requests already applied policies and permissions, but internal service calls are also security boundaries. Delivery worker promotion and order assignment now require `deliveries.manage` inside their services before a transaction begins. Direct calls cannot use an arbitrary actor to create a worker role, delivery assignment, snapshots, events or audit records.

Worker acceptance now requires both the independent `delivery-worker` role and `deliveries.accept` permission inside `DeliveryAssignmentService`. Pickup and in-transit transitions require `deliveries.update-status`, while final confirmation requires `deliveries.confirm`, in addition to matching the assigned worker. These checks are repeated against the locked delivery row where ownership matters.

Free-form `note` and `assignment_notes` inputs are excluded from flashed validation data alongside the previously protected financial and review fields. Failed requests therefore do not copy operational address/contact notes into session-backed old input.

## Verification

Focused delivery and dashboard coverage passed: **27 tests, 364 assertions**. The complete suite passed: **210 tests, 2204 assertions**. PHP syntax checks passed, and all **143 routes** retain unique names and method/URI signatures.

Tests use SQLite `:memory:` and fake private storage. No live user role, delivery assignment, state, proof or audit row was created or changed.

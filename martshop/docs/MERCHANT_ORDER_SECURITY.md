# Merchant order security

## Reservation integrity checkpoint — 2026-09-10

- Before a merchant order can consume or release inventory, every order item must have exactly one still-reserved stock record with matching order, merchant order, merchant, offer, variant and quantity fields.
- Missing, duplicated or mismatched reservation state now fails closed before stock, order status or audit history changes. The order remains pending for administrative reconciliation.
- Reservation ownership, product linkage, quantity, tracking flags and expiry are immutable through the model. Completed reservation records cannot be rewritten or deleted through Eloquent, and lifecycle transitions require their matching consumed/released metadata.
- Reservation release reasons are excluded from default array/JSON serialization. Scheduler expiration tests use direct database fixtures to simulate elapsed time without weakening production model invariants.
- Focused checkout/order/ledger coverage passed: **18 tests, 256 assertions**. The complete suite passed: **239 tests, 2647 assertions**. No migration or working-database mutation was performed.

## Authorization and reauthentication

- Confirming or rejecting a merchant order requires the current account password.
- The request policy authorizes the exact route-bound order before validation.
- `MerchantOrderService` repeats authorization after locking the current database row: the actor must still own the merchant and that merchant must still be verified.
- Authorization runs before idempotent status handling, so replaying a completed operation cannot be used to inspect or act on another merchant's order.
- Cross-merchant and suspended-merchant direct service calls fail without changing the order or its stock reservation.

## Privacy and data minimization

- Merchant order list, detail and mutation responses use private, no-store, no-referrer, anti-framing and MIME-sniffing protections.
- The list loads only the customer display-name fields and counts needed by the template; phone and delivery-address data are not loaded there.
- The detail query loads only the delivery snapshot, item fields and delivery state rendered on the page.
- Internal commission/delivery snapshots and rejection reasons are hidden from model serialization.
- Current passwords and rejection reasons are excluded from flashed old input after validation failures.

## Concurrency behavior

- Confirmation and rejection retain the existing database transaction and row lock.
- Status is checked on the locked row, expired reservations are released once, and successful reservations are consumed or restored once.
- SQLite feature coverage verifies state transitions and rollback behavior. Real simultaneous workers and production-engine deadlock behavior remain a deployment test requirement.

## Verification

- Focused merchant-order and commission integration coverage: **13 tests, 208 assertions**.
- Complete suite: **217 tests, 2353 assertions**.
- Blade compilation passed.
- All **144 routes** retain unique names and method/URI signatures.
- No migration was added or applied, and no working/live database values were changed in this checkpoint.

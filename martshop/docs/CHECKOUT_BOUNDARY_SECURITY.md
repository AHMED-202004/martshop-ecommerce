# Checkout boundary security

## Server-side enforcement

- `OrderPlacementService` now repeats critical checkout rules instead of relying only on controller validation.
- New orders are rejected when ordering is disabled, the payment method is unsupported, the checkout token is not a UUID, or the cart exceeds 100 lines.
- New checkout submissions accept only `manual_transfer`, matching the sole rendered and implemented payment flow. A crafted `cod` value is rejected independently by the HTTP validator and `OrderPlacementService`; historical orders retaining older values are not rewritten.
- Valid idempotent replays still return the customer's existing order after input format checks.
- New cart rows preserve the exact offer-variant ID. Checkout resolves that ID only within the locked selected offer, validates stored attributes and stock, and records the canonical variant snapshot; older rows without an ID use the existing unambiguous matching fallback.
- The existing transaction, row locks, stock reservations and unique checkout-token behavior remain unchanged.

## Response and model privacy

- Checkout submission responses use private account headers.
- The order confirmation page reloads only the order ID, owner ID and status; it no longer loads unused items or merchant orders.
- Delivery-address snapshots and checkout tokens are hidden from order serialization.
- Internal offer snapshots are hidden from order-item serialization.

## Disabled card endpoints

- Card entry remains disabled and the legacy form is not rendered.
- GET and POST endpoints now use strict private financial response headers.
- The POST endpoint returns a controlled 503 response without echoing submitted card values.
- Card number, expiry and CVV field names are globally excluded from session old input.

## Verification

- Latest focused checkout/payment/reservation coverage: **31 tests, 317 assertions**.
- Latest complete suite: **273 tests, 2884 assertions**.
- Focused checkout, card, cart, merchant-order and settings coverage: **23 tests, 218 assertions**.
- Complete suite: **221 tests, 2430 assertions**.
- Blade compilation passed.
- All **144 routes** retain unique names and method/URI signatures.
- No migration was added or applied, and no working/live database values were changed in this checkpoint.

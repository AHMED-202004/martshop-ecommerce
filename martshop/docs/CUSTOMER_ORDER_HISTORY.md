# Customer order history — 2026-09-08

## Relationship minimization checkpoint — 2026-09-10

- The history query now selects only the order and item fields rendered by the page. Merchant orders are represented by a count instead of hydrated records.
- Only the latest payment is loaded, with its status and optional proof identifier; older payments and unused payment-method account details are no longer loaded into the page request.
- Delivery, dispute, refund and transfer relations are constrained to the status, timestamps, references and identifiers required by the existing customer actions.
- Delivery origin/destination snapshots, assignment keys and internal notes, plus dispute reasons, are hidden from default array/JSON serialization. The authenticated owner view can still explicitly display the active PIN and customer-facing closure reason it requires.
- Focused history/order/delivery/refund coverage passed: **48 tests, 543 assertions**. The complete suite passed: **241 tests, 2662 assertions**. No migration or working-database mutation was performed.

The authenticated `/order-history` page now returns a 15-order paginator instead of loading the customer's entire history. Ordering is deterministic by newest order ID. Previous/next controls show the current and final page, emit only the page parameter, and use 44-pixel minimum targets.

## Privacy and ownership

- History continues to originate from the authenticated user's relationship; another customer's order or item does not enter the result query.
- History, direct order confirmation, and the legacy confirmation redirect now use the private-account response middleware: `no-store, private`, `no-referrer`, frame denial, MIME sniffing protection, and the storefront CSP.
- Direct confirmation still verifies `order.user_id` and returns 403 for another customer. The private headers are present on both successful and forbidden confirmation responses.
- GET history performs no business writes. Delivery PIN behavior is unchanged: an active delivery PIN is decrypted only for the owning customer's authenticated history view and disappears after delivery under the existing workflow.

## Interface

The order summary and line items use responsive grids, action groups stack on narrow screens, product images have meaningful alternative text, dates use `Y-m-d`, and amounts show the stored currency instead of assuming a shekel symbol. The return link now goes to `/my-account`, not the storefront home page.

## Verification

Five feature tests cover guest denial, own-user isolation, private headers/CSP, deterministic 15-row pagination across two pages, bounded eager-loaded reads, absence of GET writes, direct-confirmation ownership, and protected 403 responses. The fixed read budget is at most 20 SELECTs for a 15-order page; the observed suite path uses 17 including shared storefront settings and permission navigation.

Combined order/delivery/refund regression run: **65 passed, 708 assertions**. Full suite: **185 passed, 1806 assertions**. Blade compilation and PHP syntax checks passed. Fixtures use SQLite `:memory:`; no live order, delivery, payment, refund, or financial state was changed.

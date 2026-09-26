# Public input and cart security

## Contact form

- Contact pages use private, no-store responses because an authenticated page may prefill the customer's email or phone.
- Topics are restricted to the displayed server-side allowlist.
- Topic, contact value, order reference and message body are excluded from session old input after validation failures.
- Contact values, references and message bodies are hidden from model serialization.
- Submission remains available to guests and is rate-limited to five attempts per minute.

## Search

- Search terms are limited to 100 characters.
- SQL wildcard characters `%`, `_` and the escape marker are escaped and bound as data, so they search literally rather than widening the result set.
- Search queries select only the public fields rendered by result cards.
- Result cards now link to the actual product detail route.

## Cart

- Cart pages, counters and mutations use private no-store response headers.
- Mutations and polling are rate-limited according to operation cost.
- Each cart is limited to 100 distinct lines and each accumulated line quantity to 999.
- The limits are enforced inside the cart service as well as request validation.
- Route keys are limited to 200 characters, and product resolution selects only fields required to build the trusted server-side cart payload.
- Cart badges use the session-backed count rendered by Laravel. Browser `localStorage` no longer overwrites that value or increments it before a mutation succeeds; asynchronous flows update badges only with the absolute count returned by the server.
- Responses rendering the session-backed badge are verified private-cache responses. The cart's category control uses the shared public-root navigation, excluding hidden database roots and obsolete category URLs.
- The cart's displayed shipping policy and calculated fee now come from the current admin-managed checkout settings. Session rows no longer make an unconditional stock claim; availability is explicitly rechecked during order confirmation.
- The product page and public policy page share the same escaped delivery summary from marketplace settings. Unsupported static regional prices and the unimplemented `martprime` promise were removed from the policy page.
- Dead cart-page handlers for nonexistent templates/forms were removed, and the active quantity-update control is now explicitly wired as a submit button.
- Public product, policy and FAQ copy now describes the implemented manual-transfer and reviewed dispute/refund flows. Unsupported cash-on-delivery/card claims, fixed refund fees/timelines and chat-only return instructions were removed; the FAQ's orphaned cart polling script was also removed.
- Registration form hints, field lengths and browser autocomplete metadata now match the server's 12-character letters-and-numbers password rule and profile limits. The public settings API uses the same computed/custom delivery summary as the website instead of returning an empty default.
- The support widget now opens the persisted contact form instead of simulating an unsent chat. Footer support details come from escaped admin settings, unsupported card logos were removed, and permitted staff receive a private read-only contact inbox. Settings used across layouts are loaded in one bounded query.
- Contact submissions now normalize whitespace, require a valid email or bounded phone number, reject control characters, and expose browser length constraints matching server validation.
- The admin contact inbox now omits private message/contact/reference fields from list queries. A separate permission-protected detail route loads them on demand, escapes them, and audits every authorized opening.
- Disabling new merchant registration now hides customer registration entry points and suppresses the direct profile form while preserving access for existing merchants. The public API exposes the corresponding merchant/support flags and public WhatsApp setting for client parity.
- Disabling new orders now shows an operational notice throughout the storefront and removes the cart's checkout form without preventing customers from reviewing or editing saved cart rows. Disabling customer registration removes its tab and safely returns direct registration-tab visits to sign-in with a notice.
- On mobile, the populated cart table now scrolls inside a labeled region without widening the page; its category control stays in header flow instead of covering checkout content. The delete control uses the shared icon system and has a row-specific accessible label.
- The support panel no longer has duplicate fake-message handlers. Product questions open the persisted support panel or fall back to the contact route, and keyboard focus is restored when the panel closes.

## Verification

- Focused contact/search coverage: **11 tests, 167 assertions**.
- Focused cart/checkout/catalog coverage: **11 tests, 101 assertions**.
- Complete suite: **224 tests, 2487 assertions**.
- Latest cart-badge focused coverage: **27 tests, 231 assertions**.
- Preceding complete suite: **276 tests, 2907 assertions**.
- Latest cart/navigation focused coverage: **21 tests, 104 assertions**.
- Latest settings/merchant/API/account focused coverage: **31 tests, 406 assertions**.
- Latest complete suite: **282 tests, 3040 assertions**.
- Latest feature-flag/cart/account focused coverage across two runs: **42 tests, 400 assertions**.
- Current complete suite: **282 tests, 3049 assertions**.
- Responsive storefront, quick-view and support focused coverage passed; the current complete suite is **337 tests, 4630 assertions**.
- Blade compilation passed.
- All **145 routes** retain unique names and method/URI signatures.
- No migration was added or applied, and no working/live database values were changed in this checkpoint.

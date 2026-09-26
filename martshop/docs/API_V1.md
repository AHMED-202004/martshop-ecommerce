# Public API v1 — initial read-only foundation

## Query and serialization minimization — 2026-09-10

- API product endpoints now use a dedicated query that selects only the product, brand, offer and variant fields required by `ProductResource`. Internal product/offer metadata, review notes, source keys, merchant identities, exact stock and moderation state are not hydrated into API requests.
- Category collection queries select only the five fields emitted by `CategoryResource`.
- Product, offer, category and offer-variant models hide internal metadata, review/source fields and private submission-image integrity fields from default array/JSON serialization. Explicit administrative Blade views remain able to read model properties they request.
- The public response contract and sellable-offer filters are unchanged.
- Focused API/search/catalogue coverage passed: **27 tests, 393 assertions**. The complete suite passed: **243 tests, 2683 assertions**. No migration or working-database mutation was performed.

Base path: `/api/v1`. The existing Laravel application and database serve both web and API; no separate catalogue or business rules are introduced.

| Method / path | Contract |
| --- | --- |
| GET /settings | Allowlisted public site details and registration/order flags |
| GET /categories | Active categories, flat hierarchy via parent_id |
| GET /products | Active products with sellable offers |
| GET /products/{slug} | One published, sellable product; otherwise 404 |

Collections use Laravel resource envelopes: `data`, `links`, `meta`. `page` is a positive integer; `per_page` is 1–50 (defaults: categories 30, products 20). Products support `q` (literal name substring, maximum 100 characters) and `category` (exact active category path, not descendants). Prices are decimal strings, paired with an ISO currency code; never calculate payments from client-supplied prices.

Products expose only id, slug, name, image_url, brand, color, sizes, offer_id, price and currency. Merchant identities, internal metadata, documents, PINs, balances and account details are excluded. Catalogue queries and offer selection reuse `CatalogQuery` and `ProductOffer::sellable()` used by the web catalogue. No legacy fallback products are invented by the API.

Errors are JSON even without an Accept header. Validation returns 422; unavailable resources 404; unsupported writes 405; rate limiting 429 with Retry-After. Limit: 60 requests per minute per IP across v1 public routes. Review trusted-proxy configuration before deploying behind a proxy. These routes are stateless, unauthenticated and read-only. Browser cross-origin access is denied by default; set an exact comma-separated `API_CORS_ALLOWED_ORIGINS` list only for reviewed HTTPS browser clients. Credentials remain disabled and allowed CORS methods are limited to GET, HEAD and OPTIONS.

## Remaining mobile integration

This is not a complete mobile API. Add and test a maintained authentication mechanism (e.g. Sanctum), token revocation/expiry, account and per-resource policies, explicit authenticated resources and request objects before exposing orders or writes. Reuse existing order/payment/delivery services with their transaction, ownership and idempotency checks; never copy business logic into new controllers. Existing browser routes keep their session authentication and CSRF protection.

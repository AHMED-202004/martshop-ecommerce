# Public web catalogue security — 2026-09-10

## Publication and sellability

- Unknown product slugs return 404. Known legacy demo products render only when the explicit demo fallback flag is enabled; the flag defaults off so production does not expose in-controller inventory.
- `Product::publishedForStore()` centralizes the web listing rule: a product must be active, and merchant submissions must also have a currently sellable offer.
- `ProductOffer::sellable()` now permits platform offers without a merchant and merchant offers only when the associated merchant is verified. Paused, expired, out-of-stock and unverified/suspended-merchant offers are excluded consistently from API, product detail and storefront listings.
- Brand, deals and new-product pages use the shared publication rule and select only card fields plus the required brand/variant fields.
- The broken legacy `/shoes/women` query referenced columns that do not exist in the current schema; it is now a permanent redirect to the maintained `/c/shoes/women` category route.

## Filter boundaries

- Deal filters validate scalar arrays, distinct values, existing brand IDs, fixed color/size allowlists, positive pages and bounded array sizes.
- Deal search is limited to 100 characters and escapes SQL LIKE wildcard characters as literals using bound parameters.
- `is_new` is now explicitly mass assignable, matching its existing database column and model cast.

## Category route boundaries

- Category paths are limited to 500 bytes overall and 100 ASCII slug characters per segment; malformed paths return 404 before catalogue lookup.
- Product detail slugs are limited to 200 supported ASCII letters, digits, underscores or hyphens at both web and API route boundaries. New merchant-product slug generation reserves space for the uniqueness suffix so long names cannot exceed the database column.
- Category paths use a lowercase canonical URL and redirect uppercase variants permanently.
- A database category marked non-active can no longer fall back to similarly named legacy demo content.
- Active descendants of a hidden ancestor are excluded both from category pages and from `CatalogQuery`, so hiding a branch consistently hides its database-backed products.
- Once any imported category data exists, database categories are authoritative: empty database results stay empty and no longer repopulate category or gender pages with legacy demo products. Before category import, legacy display is available only through an explicit fail-closed configuration flag.
- `Category::publiclyVisible()` centralizes ancestor-aware visibility. API category listings, API product filters and database catalogue queries now reject active descendants of hidden ancestors, including literal handling for `_` and `%` in stored paths.
- Published products must either be uncategorized legacy records or have at least one publicly visible direct/pivot category. A product assigned only beneath hidden categories is excluded from product detail, search, listings and the unfiltered public API.
- Search now uses the same storefront publication scope, preventing active-but-unsellable merchant submissions from appearing in results.
- Cart item resolution uses the publication scope too, and an existing database slug is authoritative: a hidden or unsellable database record cannot fall through to same-slug legacy demo data.
- Storefront publication now requires a non-offer platform product to have `in_stock=true`; a stale session is checked against that same rule again inside checkout.
- A sellable offer remains authoritative even on a canonical product imported from the legacy catalogue, preserving the multi-vendor model.
- Product variants with `in_stock=false` are excluded from public product, brand, deal, new-product and catalogue queries. Checkout revalidates platform-product choices under row locks and rejects missing, unavailable or invented options before creating an order.
- Offer variants now have a separate `available()` rule: active variants with tracked zero stock are omitted from public product, card and API data. Checkout still locks active variants and explicitly rejects stale zero-stock selections before reserving anything.
- An offer with no variant records remains governed by its own stock. Once variant records exist, at least one must be active and non-zero-stock for the offer to remain sellable; otherwise the offer disappears from detail/list/API queries and cannot enter a new cart.

## Homepage catalogue boundaries

- The homepage now uses `HomeController` instead of a static route view.
- Root navigation and the mega menu are built only from active database categories, including active-child filtering. The legacy menu is available before import only when the explicit demo fallback is enabled.
- Featured brand cards appear only for configured presentation assets whose brand currently has at least one published product; dead or unpublished-only brand links are omitted.
- The homepage search control is now a real bounded GET form connected to the validated search route.
- Super Deals and new-product category drawers now use database-authoritative, publicly visible root categories instead of hardcoded catalogue links. Hidden roots are omitted; legacy presentation remains available only before category import.

## Public card price consistency

- Brand, Super Deals and new-product queries eager load only sellable offers ordered by price and stable ID, plus their active offer variants.
- Cards now display the same best-offer price, comparison price and offer-specific sizes used by product detail and cart resolution. Canonical product price/variants remain the fallback only when no offer exists.
- Super Deals brand filters omit brands with no currently published deal products.
- Search controls on the homepage, Super Deals and new-product pages submit to the same validated search endpoint with a 100-character browser boundary.
- Super Deals and new-product card bodies are siblings of their image wrappers, avoiding accidental nesting that distorted layout/click targets. Deals filter changes have one central submit handler rather than duplicate inline and global handlers.

## Product operational information

- Product pages no longer claim cash on delivery or hardcode regional fees and delivery estimates that can diverge from operations.
- The delivery message comes from the escaped administrator setting. When it is blank, the page builds a neutral fallback from the current configured flat fee and free-delivery threshold.
- Payment copy matches the implemented flow: manual transfer through enabled methods after merchant availability confirmation. Account identifiers remain confined to the authenticated payment step.

## Exact offer-variant selection

- Available offer variants are rendered as explicit combined choices carrying their database ID; size/color text alone is no longer used to identify a choice when an offer has variants.
- Category quick view uses the same exact choice rule, updates the visible variant price, and exposes only the public ID, size/color attributes and effective price. Its duplicate legacy event handler was removed.
- Cart resolution verifies that the submitted variant belongs to the selected sellable offer, then replaces client-supplied attributes and price with canonical variant data. The variant ID participates in the cart row key so separate choices do not merge.
- A new cart request without an ID is accepted only when its legacy attributes identify exactly one available variant (or only one variant exists); the server then stores that canonical ID. Ambiguous matches are rejected before entering the cart, while checkout keeps its fallback for pre-existing sessions.
- Checkout locks the offer's active variants, resolves the exact submitted ID, checks any stored attributes and stock, and snapshots that variant. Legacy cart rows without an ID retain the prior unambiguous attribute-matching fallback.

## Verification

- Product-slug boundary coverage passed as part of a focused run: **36 tests, 397 assertions**; the read-only working-database preflight found 771 compatible slugs and no overlength or malformed values.
- Category-navigation and homepage focused coverage passed: **14 tests, 108 assertions**.
- Offer-variant, checkout and public catalogue focused coverage passed: **37 tests, 418 assertions**.
- Exact variant/cart/checkout focused coverage passed: **39 tests, 435 assertions**.
- Category quick-view and database-category focused coverage passed: **23 tests, 148 assertions**.
- Exact variant/cart compatibility focused coverage passed: **51 tests, 494 assertions**.
- Latest listing DOM/filter focused coverage passed: **16 tests, 165 assertions**.
- All-unavailable offer focused coverage passed with the wider catalogue/cart set: **61 tests, 663 assertions**.
- Demo-fallback isolation and public catalogue focused coverage passed: **42 tests, 318 assertions**.
- The latest complete suite passed: **284 tests, 3062 assertions**.
- The complete suite passed: **277 tests, 2924 assertions**.
- Blade compilation and route uniqueness checks passed. No migration or working-database mutation was performed.

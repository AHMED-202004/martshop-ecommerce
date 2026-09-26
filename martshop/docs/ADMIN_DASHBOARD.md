# Administrative home — 2026-09-03

The new `/admin` (`admin.dashboard`) is a read-only landing page for the existing administration sections. Existing routes, workflows and header links remain intact. A **لوحة الإدارة** link is added to the signed-in storefront header and private financial-page navigation for users who can access at least one supported section.

## Access and privacy

- Access derives from existing section permissions, not the `admin` role name. No new role/permission, seeder or schema migration was required.
- Each section is added only after permission filtering, and its aggregate query is not executed for unauthorized staff. The dashboard service enforces access for direct callers as well as HTTP.
- A withdrawals-policy-only staff member sees the policy link and operating switch, not withdrawal/payout queue counts. A `refunds.cancel`-only or ordinary delivery-worker account does not gain administrative home access.
- Permissions are fetched fresh, not cached across users or requests. Removing a staff member's section permissions takes effect on the next request.
- The dashboard shares the existing no-store/private, no-referrer, frame-denial and restrictive CSP middleware/layout. It includes no chat widget, external scripts or account/proof records. Only aggregate counts and authorized section links are returned.

## Counts and links

Supported sections: merchant review, catalog moderation, payments, delivery, refund decisions, refund destination review, refund transfers, settlements/disputes, withdrawal review/policy, payment methods, commissions, merchant ledger, audit logs and site settings.

Counts use explicit work states:

- Merchants: `pending_review`.
- Catalog: merchant-created products, merchant offers and product change requests in `pending_review`; not all legacy products or drafts/changes awaiting the merchant.
- Payments: `pending` decisions.
- Delivery: merchant suborders confirmed under confirmed/paid orders, with no delivery assignment.
- Refund review: `requested`. Destinations: active `pending` destinations for requested/approved refunds. Transfers: approved and processing parent refunds; cancelled attempt history never increases request counts.
- Settlement: open disputes attached to delivered orders.
- Withdrawals: requested and approved requests, and pending payout methods; shown only with `withdrawals.approve`.

The current withdrawal and automatic-settlement switches are shown only to the relevant operational staff. The page does not enable/disable settings. Counts may overlap across workflows and are not combined into one outstanding-work or financial total. They are current operational queries, not a locked finance-closing snapshot or a substitute for action validation in the linked section.

## Verification

`php artisan test --compact`: **168 passed, 1652 assertions**; focused dashboard tests: 9 passed / 132 assertions.

Tests cover all-section access for the existing admin, valid links/empty states, private response headers, guest/customer/merchant/worker denial, restricted queue queries and secret-field exclusion, withdrawal-policy isolation, permission revocation/change on a reused user, service-level denial, live setting refresh and refund lifecycle counts across cancellation/replacement/payment. Fixtures use SQLite `:memory:`; test-only settings changes are not applied to working data.

Blade compilation and PHP syntax checks passed. The initial browser attempt was blocked by a stopped server. In the follow-up, the local server was started on `127.0.0.1:8000` and the user completed normal admin sign-in in the in-app browser. No authentication bypass or password change was performed.

Authenticated browser follow-up on 2026-09-03:

- The dashboard rendered its 15 section cards and the expected operating switches. All 14 distinct section destinations opened with their expected page titles/headings and no observed error/sign-in page; the withdrawal review and policy cards intentionally share one destination.
- Clicking the refund-transfer card opened its queue. A GET search with a test reference, processing status and oldest-first order survived navigation; clearing filters restored the empty query/default sort. No approval, payment, upload, settings save or other financial submission was performed.
- At observed desktop widths of 1280 and 915 CSS pixels, the dashboard used two columns without horizontal overflow. A narrow observation at 345 CSS pixels used one column and no measured overflowing main/section/link/metric elements. The requested fixed mobile viewport did not remain stable, and captures changed scale/crop; exact mobile-device visual verification remains partial, not passed in full.
- During the session the user navigated the retained tab to withdrawals. That navigation was left intact; no approval/rejection controls were used. The browser skill was used only for UI verification, not to infer that the pending payout method should be approved.
- Read-only counts and operating settings were rechecked: the catalog/order/refund/ledger counts below remained the same, both financial switches remained disabled, the pending payout-method count remained one, and foreign-key violations remained zero. Only this verification record was updated; application code was not changed in the browser follow-up.

Read-only observation of the working database: the designated admin receives 15 sections. All work counters were zero except **one pending merchant payout method**. It was not inspected, approved or modified. Products 771, offers 710, orders 23, merchant orders 23; refund requests/transfers/ledger entries zero; foreign-key violations zero. No application data migration or financial operation was performed for this batch.

## Responsive administration navigation — 2026-09-04

The signed-in storefront header keeps **لوحة الإدارة** directly visible and groups the authorized administration destinations inside a native **أقسام الإدارة** disclosure. The 15 dashboard sections produce 14 unique destinations because withdrawal review and withdrawal policy intentionally share one route. Links are rebuilt from current permissions on every request, without reading financial queues or aggregate counters.

The disclosure remains in normal document flow, uses a responsive grid that becomes one column below 600 CSS pixels, and keeps 44-pixel minimum action heights. Native `details`/`summary` behavior supplies Enter and Space keyboard operation; the current destination is marked with `aria-current="page"`. Guests receive neither the disclosure nor its links.

Later audit-list data minimization, private responses and authorization-before-record-lookup are documented in [AUDIT_LOG_PRIVACY.md](AUDIT_LOG_PRIVACY.md).

Feature coverage verifies the closed-by-default menu, the existing admin's 14 unique links, permission filtering and immediate revocation, withdrawal-policy isolation from financial queue queries, guest exclusion, and current-page semantics. Blade compilation passed. A fresh browser tab was redirected to normal sign-in because it did not inherit the user's authenticated session; no password was requested, stored, or bypassed. The earlier authenticated browser verification remains the visual baseline, while the responsive structure is covered by the stylesheet and feature tests.

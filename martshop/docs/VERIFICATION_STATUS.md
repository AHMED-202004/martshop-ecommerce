# Verification checkpoint — 2026-09-15

This is an incremental regression checkpoint, not production approval or a complete security audit. Subsequent work is documented in the topic files linked below; the latest complete-suite checkpoint is **424 tests / 5662 assertions**.

## Latest incremental verification

- Pre-termination confirmation (#525): the staff detail page now summarizes the exact employee, active tasks, active support tickets/chats, active deliveries, sensitive-role status and active-session count before termination. The service fails closed while active deliveries or support tickets remain assigned, continues to require password plus exact email confirmation, reassigns open staff tasks to an explicitly selected active employee, revokes sessions/tokens and preserves the historical employee identity and roles. The complete suite remained green: **424 tests, 5662 assertions**.

- Cautious bulk role changes (#519): role managers can update 2–50 selected staff only after current-password verification, a typed `BULK ROLE CHANGE` confirmation and a substantive reason. The service excludes the acting account, runs the complete batch transactionally, reuses per-employee session revocation/auditing, and rejects admin plus role/permission, payment, withdrawal, settings and audit-sensitive roles; those remain individual operations. The directory offers only routine eligible roles. The complete suite passed: **424 tests, 5662 assertions**.

- Same-department performance comparison and guardrail (#510–#511): supervisors can compare employees only when both belong to the same department and share the same operational performance type. The backend rejects manually supplied cross-department or cross-specialty targets. Comparisons use role-specific metrics and explicitly remain human-review operational aids rather than rankings or automated employment decisions. The complete suite passed: **423 tests, 5648 assertions**.

- Existing staff-management verification (#512–#518): append-only permission-scoped employee notes, name/email/phone/employee-number search with department and role filters, generated employee numbers, audited department lifecycle and manager assignment, managed-department views, and audited location/schedule scope are already implemented and covered by dedicated tests. Employee documents (#513) remain intentionally future work per the specification.

- Performance filters (#508–#509): delivery performance supports today, yesterday, week, month and a bounded custom date range, plus exact destination-area and delivery-status filters. The same centralized period resolver now governs support, catalog moderation and finance performance so date boundaries remain consistent. Custom ranges require both dates, preserve inclusive end-day behavior and reject reversed or greater-than-366-day periods. Combined filter and related performance coverage passed, and the complete suite passed: **422 tests, 5643 assertions**.

- Delivery rating and worker score (#506–#507): an order owner can submit one immutable 1–5 delivery rating only after delivery, with a private optional comment that is never copied into audit metadata. Worker performance now reports completion/failure rates, average rating and a transparent internal score; externally attributed delays do not reduce its on-time component. The UI explicitly prohibits automated punitive decisions and omits proof/policy components until trustworthy data exists. Migration `000046` passed copied-database apply/rollback/apply and was applied with clean integrity/foreign keys after backup `database-before-delivery-ratings-20260924.sqlite` (SHA-256 `92A149746ECAB8FCB7E9F4DB38BCF03588D3536BDA9C772FD28904330FD19080`). The complete suite passed: **421 tests, 5636 assertions**.

- Delivery proof policy (#505): delivery PIN and server timestamp remain mandatory, while the proof photo is now optional. When supplied, the photo retains strict image validation, private storage, size/hash verification and access auditing. Signature and courier-location collection remain disabled until an explicit consent, retention and operational policy exists. Blade compilation and the complete suite passed: **419 tests, 5617 assertions**.

- Delivery-worker assignment, availability and workload (#501–#503): administrators can assign, reassign and safely unassign queued deliveries without deleting history; workers expose an audited available/busy/break/offline state while administrators additionally control suspension. Unavailable workers are rejected at the service boundary. The private admin view shows active, queued and completed-today workload plus last activity, and explicitly does not collect current location. Migration `000045` passed copied-database apply/rollback/apply and was applied with clean integrity/foreign keys after backup `database-before-delivery-worker-workload-20260924.sqlite` (SHA-256 `9A088192FA164954B3C8F24CB8F11CECCAC6C2796275AAECBE98D114FAD55E14`). The complete suite passed: **418 tests, 5611 assertions**.

- Configurable delivery SLA and delay attribution (#495–#500): administrators can define audited, reauthenticated SLA rules by origin location, destination area, order type, delivery method and optional distance bands. Assignment snapshots the best priority/specificity match while distance-specific rules are excluded when distance is unknown. Courier/admin delay recording uses an allowlisted reason, note and responsibility (merchant, courier, customer, platform or external), and staff KPIs separate courier-responsible late deliveries from other causes. Migrations `000043` and `000044` passed copied-database apply/rollback/apply and were applied with clean integrity/foreign keys after backups `database-before-delivery-sla-rules-20260924.sqlite` (SHA-256 `AF624904885590020A95208F702EA0FAACBF594B535FB7FD01F2C2915004D407`) and `database-before-delivery-delay-attribution-20260924.sqlite` (SHA-256 `533A2165F43AABA334AA8EFB2A10A8A64CC6654C04EB2A7C2F059C4DA4206E01`). The complete suite passed: **416 tests, 5580 assertions**.

- Delivery performance and timing (#490–#494): deliveries now support guarded failed/returned/cancelled terminal outcomes with required reasons, actors and timestamps; new assignments can snapshot an expected delivery deadline for honest on-time/late metrics. Workers can record ordered merchant/customer travel and arrival milestones, and staff performance shows outcome counts, on-time rate, detailed phase averages and a minimized 50-row delivery history. Historical records without deadlines or milestones are excluded from the relevant rates instead of receiving fabricated values. Migrations `000041` and `000042` passed copied-database apply/rollback/apply and were applied with clean integrity/foreign keys after backups `database-before-delivery-outcomes-20260924.sqlite` (SHA-256 `0918F08F11A5EE3A0367FCFFABB10761C1AC26FBEE0D4A32C0A953A7A438675D`) and `database-before-delivery-milestones-20260924.sqlite` (SHA-256 `C7FE2D8199D0622BE00191CE401D8CF1B67644ABC7E399A4B14AD102D8A95AA3`). The complete suite passed: **415 tests, 5555 assertions**.

- Finance employee performance (#489): authorized staff detail pages now aggregate real payment reviews for the selected period, separating accepted, rejected, suspicious escalations, amount corrections and reconciliation discrepancies, with average review time and current pending workload. The page explicitly states that review speed cannot outrank amount/order/proof accuracy. Dedicated and related payment/staff coverage passed: **45 tests, 546 assertions**.

- Product moderator performance (#488): product, offer and product-change decisions now write an append-only decision ledger inside the moderation transaction, including reviewer, prior/next status, submission/decision timestamps, origin and reversal classification. Metrics read this history rather than only the record's latest state. Migration `000040` passed copied-database apply/rollback/apply and was applied after backup `database/backups/database-before-catalog-review-decisions-20260923.sqlite` (SHA-256 `DA12F1B6F33419C636C1662AC505794DEBDB60E81AC98B024131EF0B13778201`). Working integrity is `ok` with zero foreign-key violations. The complete suite passed: **412 tests, 5514 assertions**.

- Product moderator performance (#488.1): authorized staff detail pages now aggregate persisted product, offer and product-change reviews for the selected day/week/month, including decisions, average review time and live pending workload. Reversed decisions remain explicitly unavailable until decision lineage is persisted; the UI states that speed alone is not a quality measure. Dedicated metric coverage passed: **1 test, 9 assertions**; the wider catalog/staff/view set passed **89 tests, 1872 assertions**.

- Support employee performance (#487): the private inbox now supports permission-separated assignment, public/internal replies, guarded transitions and optimistic locking. Staff KPIs use real ticket/reply timestamps for assigned/handled/resolved/closed/reopened, response/resolution averages and SLA breaches; unsupported chat and satisfaction values are identified as unavailable. Resolution and closure actors are persisted by migration `000039`, which passed copied-database apply/rollback/apply and working integrity checks before application. Backup `database/backups/database-before-support-attribution-20260923.sqlite` SHA-256 is `C8811468A5BF805D6D732644812597424D4F3993983FC00FCB3A2E13244BFE5F`. New tickets snapshot the admin-managed first-response SLA (default 240 minutes). The complete suite passed: **411 tests, 5502 assertions**.

- Contact, checkout/address/payment, product-detail, category detail/index, brand, cart, home, new-products, deals, policies, customer order history, merchant catalogue/orders/profile, ledgers, withdrawals and financial configuration styles now use same-origin external CSS. Shared JavaScript uses `hidden` and CSS classes instead of generating runtime inline styles, while product colors use escaped SVG `fill` values. Inline style attributes are now **0** and inline style blocks are down to **19** from the original 92/60 checkpoint; repository-wide tests reject any reintroduced Blade style attribute.

- Shared storefront-shell styles now live in `public/assets/styles.css`; private financial/admin base styles, all six safe error pages, password recovery, login/registration and the account page also use same-origin external stylesheets. Password recovery now enforces `style-src 'self'` without `unsafe-inline`. Inline style attributes dropped from 92 to 85 and inline style blocks from 60 to 50. Other page-specific CSP policies still permit inline styles until their remaining styles are reviewed. The complete suite passed: **384 tests, 5239 assertions**.

- Extracted the final category-page interaction block to `public/assets/category.js`; its minimized catalogue payload and URL bases are passed through HTML-escaped data attributes. A repository-wide view regression test rejects executable inline Blade scripts. Public storefront and private-account CSP now use same-origin scripts without `unsafe-inline`; inline styles remain explicitly allowed pending a separate CSS extraction. Focused CSP/catalogue coverage passed: **51 tests, 1622 assertions**. The complete suite passed: **377 tests, 5197 assertions**.

- Storefront CSP preparation moved fixed support, merchant-product-mode, checkout-payment, product-detail, cart and new-products behavior from Blade templates into `public/assets/app.js`. Home category-menu and brand quick-view payloads now use escaped hidden HTML data carriers rather than executable inline globals. Only the category-page interactive block remains inline and is intentionally deferred to a separate reviewed extraction. JavaScript syntax, Blade compilation and affected feature coverage passed. The complete suite passed: **376 tests, 5107 assertions**.

- Added `php artisan app:production-readiness`, a read-only release gate for production environment/debug/HTTPS/key/trusted-host, UTC, session, asynchronous after-commit queue, catalogue fallback, CORS and password-recovery mail settings. It reports setting names only, never configured values. Focused coverage passed: **3 tests, 11 assertions**. The current local development environment correctly fails the production gate and was not modified. The complete suite passed: **375 tests, 5091 assertions**.

- Local lockfile advisory checks on 2026-09-15 passed: `composer audit --locked --no-interaction` reported no advisories, and `npm audit --package-lock-only --audit-level=moderate` reported zero vulnerabilities. `npm run build` passed and generated the Vite manifest/assets without changing dependency lockfiles. This is a point-in-time advisory/build check, not production approval or proof that runtime configuration is safe.

- Managed-department manager/employee lookup indexes passed copied-database apply/rollback/apply and were added to the working database after a verified backup. SQLite query plans use both indexes. A schema regression test now checks them. The complete suite passed: **372 tests, 5080 assertions**; working SQLite integrity is `ok` with zero foreign-key violations.

- Managed-department open-task scoping now happens in the database rather than through a loaded employee-ID list. Staff and task queries share the same operational-account predicate; regression tests confirm that an employee's open task follows their current department after transfer and that a permitted manager without departments sees no external staff or tasks. The complete suite passed: **371 tests, 5078 assertions**.

- The authorized staff security summary now explicitly states that two-factor authentication is not available yet; no unimplemented protection is implied. The complete suite passed: **369 tests, 5061 assertions**.

- Managed-department task previews now explicitly report the full filtered count while loading at most 100 records. Task deadline forms and read-only task views label their UTC interpretation so the employee's separate weekly-schedule timezone cannot be mistaken for the task deadline timezone.
- The complete suite passed: **369 tests, 5059 assertions**. Blade compilation passed; all **168 routes** have unique names and method/URI signatures; working SQLite integrity is `ok` with zero foreign-key violations.

- Managed-department managers now see a bounded, minimized list of open tasks assigned only to operational staff in their own departments, an allowlisted overdue-only filter, and aggregate monthly delivery counts without customer/order records. Ordinary customer accounts accidentally linked to a department are excluded from both staff and task views.
- Employee own-task cards show the related record type and numeric ID without loading that record or its private content. The complete suite passed: **368 tests, 5052 assertions**.

- Safe standalone Arabic error pages now cover forbidden, missing, expired-session, rate-limited, unexpected-error and maintenance responses (`403`, `404`, `419`, `429`, `500`, `503`). They contain no exception/path detail and use only trusted internal navigation.
- The complete suite passed: **368 tests, 5024 assertions**. Blade compilation passed; all **168 routes** have unique names and method/URI signatures; working SQLite integrity is `ok` with zero foreign-key violations.

- The employee task page now shows only the signed-in employee's location scope, weekly schedule and timezone, plus an aggregate open/overdue/completed-today summary. Overdue work is derived from the live deadline, visibly marked on each card and filterable with an allowlisted query value; manager-only notes remain unloaded.
- A safe Arabic `429` page now handles rate limiting without framework details or an untrusted referrer link.
- The complete suite passed: **366 tests, 5008 assertions**. Blade compilation passed; all **168 routes** have unique names and method/URI signatures; working SQLite integrity is `ok` with zero foreign-key violations.

- Failed login summary fields now record the last failed attempt and the count since the most recent successful login without storing IP or device data. Successful authentication atomically clears the current warning count; unknown identifiers receive the same response and create no account record.
- Migration `000036` passed copied-database apply/rollback/apply checks and was applied after backup `database/backups/database-before-failed-login-summary-20260915.sqlite` (SHA-256 `8787031C6908699DF7CBC0C62146AF533486F26F13904A9169C787088D6271BA`). Existing accounts started at zero with no fabricated timestamp.
- The complete suite passed: **366 tests, 4987 assertions**. Blade compilation passed; all **168 routes** have unique names and method/URI signatures; working SQLite integrity is `ok` with zero foreign-key violations.

- Safe Arabic `403` and `404` pages replace framework-default HTML without exposing exception classes or local paths; authenticated forbidden responses retain private no-store/frame/referrer headers.
- Password-change time is now persisted for registration, invitation acceptance, token reset and authenticated password change, hidden from default serialization, and shown in the authorized staff security summary. Existing accounts remain null rather than receiving fabricated history.
- Migration `000035` passed copied-database apply/rollback/apply checks and was applied after backup `database/backups/database-before-password-change-summary-20260915.sqlite` (SHA-256 `86F568F7104A23DB551EDB640C4A649A81DD405EBD35DAD5EE590DF020C17B78`).
- The complete suite passed: **366 tests, 4980 assertions**. Blade compilation passed; all **168 routes** had unique names and method/URI signatures; working SQLite integrity was `ok` with zero foreign-key violations.

- Staff location scope and complete weekly schedules are persisted and audited. A non-empty delivery-worker scope is enforced at the assignment service boundary, while an empty scope remains explicitly unrestricted for backward compatibility. The staff directory shows an aggregate open-task count without loading task bodies, and the admin dashboard summarizes active operational staff plus open and overdue tasks.
- Migration `000034` passed copied-database apply/rollback/apply checks and was applied after backup `database/backups/database-before-staff-scope-schedule-20260914.sqlite` (SHA-256 `55F5514EF0EB0C0545B954DD260926C8ABC605F16F595DB4801A9A91D712C62A`).
- The complete suite passed: **364 tests, 4951 assertions**. Blade compilation passed; all **168 routes** have unique names and method/URI signatures; working SQLite integrity is `ok` with zero foreign-key violations.

- Staff notes are append-only and permission-separated; manager-scoped department summaries exclude cross-department and private identity data; delivery performance uses only persisted milestone timestamps; termination can atomically reassign open staff tasks while deliveries remain a hard blocker.
- Migrations `000032` and `000033` passed copied-database apply/rollback/apply checks. Their working backups have SHA-256 `5B56446077B13C5D41F4CB4F6DF9AD48E6EAC7E1F75A02724C4C837726434001` and `E88BA8665849E1BEC78799183335BACE0A4A12EB2C24A037B6E3C6F40E69AFC6`.
- The complete suite passed: **359 tests, 4911 assertions**. Blade compilation passed; all **167 routes** have unique names and method/URI signatures; working SQLite integrity is `ok` with zero foreign-key violations.

- Internal staff tasks now support guarded assignment, priority, due dates, status transitions, reassignment, related-record validation, workload counts, a minimized central queue, and isolated employee self-service. Closed tasks are immutable, stale versions are rejected, open work blocks termination, and audit summaries omit task descriptions and internal manager notes.
- Task migrations `000030` and `000031` passed copied-database apply/rollback/apply checks. Working backups have SHA-256 `10B022F53082EDE5CF42CFA55A3C665D4B81DE1E9003F4F36B1307F5F20C6FDE` and `F98846FAD6FDF79C492143753DF9756A6DB8E8799A03930C60D2AEB191028B52` respectively.
- The complete suite passed: **349 tests, 4813 assertions**. Blade compilation passed; all **164 routes** have unique names and method/URI signatures; working SQLite integrity is `ok` with zero foreign-key violations.

- Staff departments and employee identity are now database-backed. Department create/update/disable/manager assignment and staff profile changes require `roles.manage`, reauthentication, a reason, service-boundary authorization, and append-only audit records. Employee numbers are immutable and assigned to existing/new operational staff. Directory search/filter/sort and direct-permission dashboard navigation are covered.
- Migration `2026_09_14_000029_create_staff_departments_and_add_staff_identity.php` passed copied-database apply/rollback/apply plus integrity and foreign-key checks. Working backup SHA-256: `AB144D6EC4790C96774C1C3653F0D2984171D40AFCDF3A5643641DE690E45925`.
- The complete suite passed: **342 tests, 4714 assertions**. Blade compilation passed; all **159 routes** have unique names and method/URI signatures; working SQLite integrity is `ok` with zero foreign-key violations.

- Staff security summaries now persist only the last successful login timestamp and successful-login count, without IP/device tracking or fabricated backfill. Failed login attempts do not increment the count. The migration passed copy-based rollback testing and was applied to the backed-up working database.
- The complete suite passed: **337 tests, 4630 assertions**. There are **155 routes**, with no duplicate names or method/URI signatures; Blade compilation and scoped formatting passed.
- Staff invitations now create non-login-capable `invited` accounts and use trusted-origin, hashed, expiring, single-use password setup links. Resending invalidates the prior token; transport failure removes unusable tokens without logging them; accepted invitations activate the account. Leave and guarded termination workflows are implemented, and non-active accounts now fail permission checks at the model/service boundary. Active delivery work blocks termination, and unavailable workers cannot receive new assignments.
- The complete suite passed: **336 tests, 4618 assertions**. There are **155 routes**, with no duplicate names or method/URI signatures; Blade compilation, scheduler checks and scoped formatting passed.
- Staff managers can now change operational roles while preserving customer/merchant identity roles and can manage additive direct permissions. Changes are reauthenticated, reasoned, audited, session-revoking, immediately enforced by backend permission checks, and protected against self-change or disabling the last active administrator. The direct-permission migration passed copy-based rollback testing and was applied to the backed-up working database with zero initial assignments.
- The complete suite passed: **328 tests, 4489 assertions**. There are **151 routes**, with no duplicate names or method/URI signatures; Blade compilation and scoped formatting checks passed.
- Operational staff management now has a permission-scoped directory/detail view, effective-permission sources, minimized session/security history, targeted session revocation, and reauthenticated audited suspension/reactivation. Suspended accounts cannot log in and loaded web sessions are invalidated on their next request. The account-status migration passed copy-based rollback testing and is applied to the backed-up working database; see [STAFF_MANAGEMENT_SECURITY.md](STAFF_MANAGEMENT_SECURITY.md).
- The complete suite passed: **321 tests, 4425 assertions**. There are **149 routes**, with no duplicate names or method/URI signatures; Blade compilation and scoped formatting checks passed.
- Payment, delivery, withdrawal and refund proof downloads now share one fail-closed path/size/SHA-256 verifier while retaining their type-specific filename rules. The working database's five payment proofs and two delivery proofs passed a read-only integrity scan.
- Critical reservation and settlement schedules now use bounded overlap-lock lifetimes (5 and 15 minutes) and have structural regression coverage. The real database cache lock was acquired and released successfully.
- The four integrity/login migrations passed a copy-based `migrate → rollback → migrate` test and were then applied to the backed-up working SQLite database. Three valid historical merchant documents received audited SHA-256 backfills; zero integrity gaps or foreign-key violations remain. See [WORKING_DATABASE_MIGRATION_20260913.md](WORKING_DATABASE_MIGRATION_20260913.md).
- Login and registration now use named rate limiters, including a normalized hashed login identifier. All administrative, merchant and delivery mutations are structurally tested for authentication, private-response headers and rate limiting.
- Product-change image storage now enforces MIME and size limits even on direct service calls. Payment proofs reject non-local disks before resolving storage, audit request metadata is schema-bounded, and excessive or malformed pagination is rejected before database queries.
- Database category navigation is built with DOM text nodes instead of `innerHTML`; the obsolete static JavaScript category tree was removed. A production deployment checklist and secure new-install session defaults were added without changing the active local session or working database.
- Locked PHP and Node dependencies were updated within their existing major-version constraints. Laravel is now **12.69.2**, PHPUnit **11.5.56**, and Vite **7.3.6**. `composer audit` and `npm audit` both report **0 known vulnerabilities**, the production frontend build succeeds, and optimized Composer autoloading has no PSR-4 warnings.
- Baseline browser security headers now apply to public responses without weakening stricter private-page headers. Unused vendor routes that could serve or upload files on the private local disk were disabled; there are **145 routes**, with no duplicate names or method/URI signatures.
- The complete suite passed: **311 tests, 4282 assertions**. The production asset build passed and both locked Composer and npm audits reported zero known vulnerabilities.
- Category and brand quick-view data now use JavaScript-safe server serialization instead of quoted JSON attributes. Product/cart forms no longer submit client-controlled product names, prices or images; obsolete templates, unused quick-view payloads and fake cart controls were removed. Brand quick view and the new-products category drawer gained dialog/focus/keyboard semantics and were exercised in the in-app browser.
- The complete suite passed: **287 tests, 3112 assertions**. All **146 routes** retain unique names and method/URI signatures; JavaScript syntax and Blade compilation passed.
- Focused in-app browser checks removed homepage overflow at 320px/360px-class widths, kept populated-cart overflow inside a labeled table region, removed cart-heading overlap, restored mobile category navigation, and verified support-panel keyboard focus. Dead duplicated homepage mega-menu and simulated-chat JavaScript were removed. Focused Blade/feature checks passed, and the browser measurements are recorded in RESPONSIVE_UI_VERIFICATION.md.
- The complete suite passed: **286 tests, 3081 assertions**. All **146 routes** retain unique names and method/URI signatures; JavaScript syntax and Blade compilation passed.
- Legacy storefront products and category navigation are now controlled by a fail-closed configuration flag that defaults off. With fallback disabled, demo category/product URLs return no catalogue and demo cart additions are rejected; database-backed catalogue paths remain authoritative. Focused catalogue coverage passed: **42 tests, 318 assertions**.
- The complete suite passed: **284 tests, 3062 assertions**. All **146 routes** retain unique names and method/URI signatures; PHP syntax, JavaScript syntax and Blade compilation passed.
- The default database seeder now installs only repeatable foundation data and cannot create the old test account or demo catalogue. Legacy catalogue import remains an explicitly named disposable-local operation. Focused seeder/import/authorization coverage passed: **7 tests, 41 assertions**.
- The complete suite passed: **283 tests, 3055 assertions**. All **146 routes** retain unique names and method/URI signatures; PHP syntax, JavaScript syntax and Blade compilation passed.
- Disabling new orders now publishes a storefront-wide operational notice and removes the checkout form while preserving cart review/editing. Disabling customer registration removes its tab; direct registration-tab links return to sign-in with a clear notice. Both operations remain enforced server-side. Focused coverage passed: **42 tests, 400 assertions** across the two focused runs.
- The complete suite passed: **282 tests, 3049 assertions**. All **146 routes** retain unique names and method/URI signatures; JavaScript syntax and Blade compilation passed.
- Disabling new merchant registration now removes customer entry links and the direct registration form while preserving established merchant access. Public API clients receive matching merchant-registration/support flags and the public WhatsApp setting. Focused coverage passed: **31 tests, 406 assertions**.
- The complete suite passed: **282 tests, 3040 assertions**. All **146 routes** retain unique names and method/URI signatures; JavaScript syntax and Blade compilation passed.
- The admin contact list no longer queries or renders contact values, order references or message bodies. A separate authorized detail page loads private content on demand, escapes it and records each opening in the append-only audit log; unauthorized requests are rejected before the private record query. Focused coverage passed: **19 tests, 248 assertions**.
- The complete suite passed: **282 tests, 3028 assertions**. All **146 routes** retain unique names and method/URI signatures; JavaScript syntax and Blade compilation passed.
- Contact submissions now trim stored values, validate a real email or bounded phone number, reject control characters, and publish HTML limits matching server validation. Focused contact/admin/settings coverage passed: **20 tests, 267 assertions**.
- The complete suite passed: **282 tests, 3015 assertions**. All **145 routes** retain unique names and method/URI signatures; JavaScript syntax and Blade compilation passed.
- The support widget now routes customers to the persisted contact form instead of simulating unsent chat messages. Escaped admin-managed contact details appear in the footer, unsupported card logos were removed, and a dedicated-permission private read-only contact inbox was added to the admin dashboard. Marketplace settings are now loaded in one bounded query per service instance. Focused coverage passed: **31 tests, 365 assertions**.
- The complete suite passed: **282 tests, 3005 assertions**. All **145 routes** retain unique names and method/URI signatures; JavaScript syntax and Blade compilation passed.
- Registration form guidance and HTML limits now match the server's 12-character letters-and-numbers password policy and profile constraints. The public settings API now returns the same computed or admin-customized delivery summary used by the website. Focused registration/API/settings coverage passed: **35 tests, 415 assertions**.
- The complete suite passed: **280 tests, 2973 assertions**. All **144 routes** retain unique names and method/URI signatures; JavaScript syntax and Blade compilation passed.
- Public product, policy and FAQ content now matches the implemented manual-transfer and reviewed dispute/refund workflows. Unsupported cash-on-delivery/card promises, fixed refund fees/timelines, chat-only return directions and fixed delivery estimates were removed. The orphaned FAQ cart-polling script was also removed. Focused payment/refund/public coverage passed: **41 tests, 442 assertions**.
- The complete suite passed: **279 tests, 2961 assertions**. All **144 routes** retain unique names and method/URI signatures; JavaScript syntax and Blade compilation passed.
- Product detail and public policy pages now use the same escaped delivery summary from marketplace settings. The policy page no longer advertises unsupported static regional fees or the unimplemented `martprime` tier. Dead cart handlers targeting nonexistent templates/forms were removed, and the active quantity update button is explicitly wired. Focused public-policy/cart coverage passed: **32 tests, 280 assertions**.
- The complete suite passed: **279 tests, 2947 assertions**. All **144 routes** retain unique names and method/URI signatures; JavaScript syntax and Blade compilation passed.
- The cart page now calculates and explains shipping from the current admin-managed fee and free-shipping threshold rather than hard-coded template values. It also avoids claiming that stale session rows are in stock and states that availability is rechecked at order confirmation. Focused coverage passed: **21 tests, 165 assertions**.
- The complete suite passed: **279 tests, 2937 assertions**.
- The cart page category control now uses the database-authoritative public root navigation (with the pre-import fallback), omits hidden roots, and links Super Deals to its maintained route instead of stale `/c/super-deals` and `/c/fashion` paths. Public pages that render a session cart count are also covered as private-cache responses. Focused coverage passed: **21 tests, 104 assertions**.
- The complete suite passed: **278 tests, 2931 assertions**.
- Offers without variants retain their stock-based behavior; offers that define variants now require at least one active non-zero-stock variant to be sellable. All-unavailable offers are omitted consistently from detail/list/API data and rejected by cart resolution. JSON cart failures now return structured 422 validation errors. Focused coverage passed: **61 tests, 663 assertions**.
- The preceding complete suite passed: **277 tests, 2924 assertions**.
- Super Deals and new-product cards now close their image wrappers before the price/name body, preventing nested click/layout behavior. The duplicate inline Deals filter submission handler was removed in favor of the existing central handler. DOM structure and focused catalogue coverage passed: **16 tests, 165 assertions**.
- The preceding complete suite passed: **276 tests, 2912 assertions**.
- Cart badges now render from the Laravel session and accept only confirmed absolute counts from server responses/polling. Legacy `localStorage` overrides and optimistic click/submit increments were removed, so failed cart mutations cannot leave a false badge. Focused coverage passed: **27 tests, 231 assertions**.
- The preceding complete suite passed: **276 tests, 2907 assertions**.
- New cart additions resolve a unique legacy size/color choice to its canonical offer-variant ID, auto-select a sole available variant, and reject ambiguous choices unless the exact ID is submitted. Existing session rows retain checkout compatibility. Focused coverage passed: **51 tests, 494 assertions**.
- The preceding complete suite passed: **275 tests, 2901 assertions**.
- Category quick view now submits the exact available offer-variant ID and canonical combined size/color choice, changes its displayed variant price, omits zero-stock/private variant fields, and has only one modal/filter event binding. Focused coverage passed: **23 tests, 148 assertions**.
- The preceding complete suite passed: **275 tests, 2897 assertions**.
- Delivery confirmation now repeats PIN/note/file type and 10MB size validation inside the authorized service boundary; the HTTP request reuses the same rules. Product-submission image storage independently verifies detected JPEG/PNG/WEBP MIME and the 5MB byte limit before writing. Focused coverage passed: **46 tests, 532 assertions**.
- The complete suite passed: **274 tests, 2888 assertions**.
- Offer choices on product pages now carry the exact offer-variant ID. Cart rows include that ID in their identity, canonicalize attributes and variant pricing server-side, and checkout verifies ownership/status/attributes/stock under its existing locks before snapshotting the selected variant. Focused coverage passed: **39 tests, 435 assertions**.
- The complete suite passed: **273 tests, 2884 assertions**.
- New checkout requests now accept only the implemented `manual_transfer` flow at both HTTP and service boundaries; crafted `cod` submissions fail validation while historical order values remain readable. Focused coverage passed: **31 tests, 317 assertions**.
- The complete suite passed: **271 tests, 2867 assertions**.
- Product pages now use escaped administrator delivery text (or current fee/threshold settings as fallback) and describe the implemented post-confirmation manual-transfer flow instead of hardcoded delivery/payment claims.
- Zero-stock offer variants are excluded from public product/card/API data while checkout continues to lock and reject stale zero-stock selections. Focused coverage passed: **37 tests, 418 assertions**.
- The complete suite passed: **270 tests, 2863 assertions**.
- Product slugs are bounded to 200 supported ASCII characters at creation and at both web/API route boundaries; a read-only working-database preflight found all 771 existing slugs compatible. Focused coverage passed: **36 tests, 397 assertions**.
- Super Deals and new-product category drawers now use database-authoritative, publicly visible root categories and omit hidden roots. Blade compilation and focused coverage passed: **14 tests, 108 assertions**.
- The complete suite passed: **268 tests, 2846 assertions**.
- Brand, deal and new-product cards now use the same best sellable offer price and variants as detail/cart flows; focused coverage passed: **30 tests, 316 assertions**.
- The complete suite passed: **265 tests, 2826 assertions**.
- Products assigned only to hidden category branches are now excluded from all public publication and merchant offer creation paths; focused coverage passed: **39 tests, 464 assertions**.
- The complete suite passed: **264 tests, 2801 assertions**.
- Ancestor-aware category visibility now covers public API filters and every merchant category submission/review boundary; focused coverage passed: **36 tests, 400 assertions**.
- The complete suite passed: **262 tests, 2789 assertions**.
- Database-authoritative category pages and active-only homepage category/brand navigation passed focused coverage: **22 tests, 99 assertions**.
- The complete suite passed: **260 tests, 2774 assertions**.
- Product and variant availability is now enforced consistently from public views through checkout; focused coverage passed: **43 tests, 434 assertions**.
- The complete suite passed: **255 tests, 2752 assertions**.
- Search publication enforcement and fail-closed cart resolution for database/legacy slug collisions passed focused coverage: **27 tests, 278 assertions**.
- The complete suite passed: **251 tests, 2725 assertions**.
- Category path validation/canonicalization, hidden-branch fail-closed behavior and catalogue-query inheritance checks passed focused coverage: **21 tests, 211 assertions**.
- The complete suite passed: **250 tests, 2717 assertions**.
- Public storefront publication/sellability rules and bounded deal filters passed focused coverage: **18 tests, 236 assertions**.
- The complete suite passed: **247 tests, 2708 assertions**.
- Public API query and catalogue serialization minimization passed focused coverage: **27 tests, 393 assertions**.
- The complete suite passed: **243 tests, 2683 assertions**.
- Customer order-history relationship/serialization minimization passed focused coverage: **48 tests, 543 assertions**.
- The complete suite passed: **241 tests, 2662 assertions**.
- Merchant-order stock reservation integrity and immutable lifecycle coverage passed: **18 tests, 256 assertions**.
- The complete suite passed: **239 tests, 2647 assertions**.
- Login-phone database uniqueness and fail-safe migration coverage passed: **22 tests, 178 assertions**.
- The complete suite passed: **237 tests, 2633 assertions**.
- Account/address reauthentication, registration validation, login normalization and user serialization privacy passed focused coverage: **33 tests, 311 assertions**.
- The complete suite passed: **235 tests, 2631 assertions**.
- Refund review/destination authorization and page/serialization minimization passed focused coverage: **21 tests, 252 assertions**.
- The complete suite passed: **229 tests, 2571 assertions**.
- Withdrawal page and serialization minimization passed focused coverage: **17 tests, 306 assertions**.
- The complete suite passed: **228 tests, 2566 assertions**.
- Payment-review and ledger service authorization plus payment-page minimization passed focused coverage.
- The preceding complete suite passed: **228 tests, 2557 assertions**.
- Financial proof storage verification, refund-proof path confinement and withdrawal service authorization passed focused coverage: **59 tests, 719 assertions**.
- The preceding complete suite passed: **227 tests, 2525 assertions**.
- Contact/search privacy and literal-query handling plus cart privacy and service limits passed focused coverage.
- The preceding complete suite passed: **224 tests, 2487 assertions**.
- Checkout boundary enforcement, confirmation minimization and disabled-card privacy passed focused coverage: **23 tests, 218 assertions**.
- The preceding complete suite passed: **221 tests, 2430 assertions**.
- Merchant KYC submission privacy, reauthentication, minimization and service authorization passed focused coverage: **11 tests, 112 assertions**.
- The preceding complete suite passed: **218 tests, 2393 assertions**.
- Merchant-order page privacy, reauthentication, data minimization and service authorization passed focused coverage: **13 tests, 208 assertions**.
- The preceding complete suite passed: **217 tests, 2353 assertions**.
- Merchant catalog service authorization, page privacy and private pending-image controls passed focused coverage: **23 tests, 230 assertions**.
- The preceding complete suite passed: **216 tests, 2312 assertions**.
- Catalog moderation privacy, reauthentication, service authorization and self-review protections passed focused coverage: **19 tests, 182 assertions**.
- Product-change image security and catalog/schema integration coverage passed: **18 tests, 168 assertions**.
- Blade compilation passed, and all **144 routes** retain unique names and method/URI signatures.
- Product-change, pending-product and merchant-document integrity migrations are applied to the working database; current stored private images have complete integrity metadata.
- The login-phone uniqueness migration is applied; there are no duplicate non-null login-phone groups.

## Executed

- `php artisan test`: **92 passed, 785 assertions**. Database feature tests use SQLite `:memory:` per `phpunit.xml`; no migration or data change was applied to the working database in this checkpoint.
- `php artisan view:cache`: all Blade templates compile successfully.
- Read-only working database check: 771 products, 710 offers, 23 orders, 23 merchant orders, zero foreign-key violations. The requested admin account remains assigned. Withdrawals remain disabled. These are observed counts, not evidence that this checkpoint created records.

## Regressions reproduced and fixed

1. Invalid settings booleans silently became false. Submitted invalid values now fail validation; omitted HTML checkboxes still mean false.
2. Layout settings were loaded only when the application booted. A layout composer now reads current notice/chat settings when rendering, including subsequent requests within a reused application instance.
3. Audit actor searches containing underscores did not match literally on SQLite. Searches now use bound parameters with an explicit LIKE escape character.
4. Audit redaction matched the `pin` substring inside `shipping`, while camel-case identity fields could escape redaction. Matching now uses normalized key boundaries and covers nested data. This key-based display filter is defense in depth, not a substitute for keeping secrets out of audit payloads.

## Added coverage

- Invalid booleans leave settings and audit records unchanged.
- Updated chat/notice settings reach rendered views; notice HTML is escaped.
- Audit end-date-only filtering, literal actor searches, and nested secret redaction.
- Delivery settlement failure rolls back status/events/proof records and cleans up the newly uploaded test file.
- Delivery confirmation rejects another worker, stale versions, and unsupported files; PIN fields stay encrypted at rest and excluded from model serialization.
- Public API searches treat wildcard characters literally and bind SQL-like input as data.

## Remaining release gates

- **Financial hold (subsequently implemented):** delivery now snapshots a configurable dispute deadline, open disputes move funds to held, and release is delayed/idempotent. Automatic release and withdrawals remain disabled. Review the operating policy and refund limitations in `DELAYED_SETTLEMENT.md` before enabling real-money operation.
- Public API v1 is read-only. Mobile authentication, token expiry/revocation, authenticated resources and writes remain unimplemented; see `API_V1.md`.
- Real simultaneous workers, deadlock retries (including private-file side effects), and a production database engine were not exercised by this SQLite test suite.
- Focused browser-driven checks cover selected public storefront flows only. Full authenticated customer, merchant, delivery and administrator end-to-end coverage remains a release gate.
- Before public deployment, review HTTPS, debug mode, proxy trust, CSRF/session settings, upload handling, scheduler/queue operation and backup restoration in the actual hosting environment. Do not infer production readiness from this local run.

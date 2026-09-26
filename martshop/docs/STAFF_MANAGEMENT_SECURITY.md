# Staff management security

This increment adds a permission-scoped operational staff directory and account-security controls. It is not a complete employee-management system.

## Implemented

- `roles.manage` is required at the HTTP controller, form-request and service boundaries.
- The directory includes only operational users: administrators, delivery workers, or roles with at least one permission. Search treats SQL wildcard characters literally; role and account-status filters are allowlisted and paginated.
- Staff detail pages show minimized identity fields, role-derived effective permissions, active database-session counts, and the latest 50 account/session security events. Session payloads, IP addresses and device strings are neither selected nor rendered.
- Staff detail pages also show `last_login_at`, a successful-login count, the last real password-change time, and failed attempts since the last successful login in UTC. A successful login clears the current failed-attempt count; the last failure time remains for review. These fields are excluded from default user serialization, and no login IP or device fingerprint is stored.
- Authorized managers can replace a staff member's operational roles while preserving customer/merchant identity roles. They can also grant or revoke direct additive permissions; the effective-permissions view identifies every role source and the `مباشرة` source. Both actions require reauthentication and a reason, apply immediately at backend authorization checks, revoke stored sessions, rotate the remember token, and append an audit event.
- Staff cannot change their own role, direct permissions, status, or sessions through these administrative actions. The last active administrator cannot be suspended or lose the administrator role.
- Account states are represented by `invited`, `active`, `on_leave`, `suspended`, and `terminated`. Existing and newly registered users default to `active`.
- Administrators can place active staff on leave or suspend them, then reactivate them. Leaving active status requires the acting administrator's current password and a reason, prevents new login, invalidates a loaded web session on its next request, deletes stored database sessions, rotates the remember token, and writes an append-only audit event in the same transaction.
- New staff invitations require working password-recovery mail, reauthentication, a reason, at least one operational role, and optional direct permissions. The account starts as `invited` with an unknown random password. A hashed, expiring, single-use token is mailed through the trusted configured origin; accepting it sets a strong password and activates the account. Administrators can resend only pending invitations, which invalidates the previous token. Failed delivery removes the unusable token and is audited without logging it. Expired reset/invitation tokens are cleared by the daily scheduler.
- Termination is a separate confirmed action requiring the employee email, administrator password, and reason. It is rejected for the current actor, the last active administrator, or a worker with any undelivered assignment. Successful termination revokes sessions and reset/invitation tokens, rotates the remember token, preserves roles and historical records, and blocks both web and direct service authorization.
- Session revocation is a separate reauthenticated, reasoned and audited action. It affects only the selected operational employee.
- Terminated accounts cannot be reactivated or have roles/direct permissions changed through the current workflow. Delivery assignment now requires an active worker with effective delivery permission and excludes unavailable workers from the assignment list.
- Staff departments are database-managed rather than hard-coded. Authorized managers can create, rename, disable, and assign an active operational manager to a department. Every change requires reauthentication and a reason and is append-only audited. A department never grants a permission by itself.
- Operational staff have a unique immutable internal number (`EMP-000001` format), optional job title, phone, and department. Existing operational accounts were backfilled deterministically; invitations and delivery-worker promotions assign the number automatically. Profile changes preserve the number and permissions and are audited.
- The directory supports literal search by name, email, phone, or employee number, plus allowlisted role, status, department, and sort filters. Direct permissions are included in dashboard/navigation authorization immediately, matching backend permission checks.
- Administrators can create, prioritize, schedule, reassign, progress, complete, or cancel internal staff tasks. Assignments are limited to active operational staff, status transitions are allowlisted, closed tasks stay immutable, and optimistic task versions reject stale forms. Task audit summaries omit descriptions and internal notes.
- The central task queue is permission-scoped, paginated and minimized. Staff with the two explicit own-task permissions can see only their assignments and update only progress states; they cannot cancel, reassign, change priority, view another employee's task, or load manager-only notes.
- The employee task page also shows that employee's own location scope and weekly schedule, with aggregate open, overdue and completed-today counts. Overdue status is derived from the current deadline rather than persisted as a stale workflow state, and an allowlisted filter can isolate overdue work. A related record is shown by type and numeric ID only; its private content is never loaded by this view.
- Open staff tasks block termination until they are reassigned. Tasks may reference an allowlisted operational record by numeric ID; the server derives the model class from the task type and never accepts a PHP class name from input.
- Termination can atomically reassign every open staff task to a selected active operational replacement, incrementing each task version and appending an audit record. Active deliveries remain a separate hard blocker and must use the delivery workflow.
- Internal staff notes use separate view/manage permissions, reauthentication on creation, append-only records, escaped rendering, and audit summaries that contain only the note ID—not the note body. Unauthorized staff-detail requests do not query note text.
- Delivery-worker performance is calculated only from persisted assignment, acceptance, pickup and delivery timestamps for an allowlisted day/week/month period. The system deliberately does not fabricate merchant-arrival, customer-arrival, delay-attribution or SLA metrics that are not yet stored.
- A manager with the explicit managed-department permission gets a minimized read-only view of only departments whose `manager_id` matches their account. It includes operational employees only, bounded open-task summaries and aggregate monthly delivery counts, with an allowlisted overdue filter. Open tasks are scoped to the assignee's current managed department in the database, so transfer removes them from the old manager's view. Email, phone, permissions, notes, customer/order records and other departments are not loaded.
- The current `is_active` department flag is a status label, not an authorization revocation. Disabling a department does not clear its manager or employees, and a still-permitted manager retains its read-only view. To revoke that view, remove the manager assignment or the explicit `staff-departments.view-managed` permission. Any different disable/revocation policy requires an explicit product decision.
- The manager's task preview reports the complete filtered total even when it loads only the first 100 records. Task deadline inputs and displays are labeled UTC; the separate employee weekly schedule uses its explicitly configured timezone.
- Authorized staff managers can assign active marketplace locations and a complete seven-day work schedule to an operational employee. The update is atomic, reauthenticated, reasoned and audited; inactive locations, incomplete schedules and terminated targets are rejected.
- Delivery assignment enforces a worker's non-empty location scope against the merchant order's origin location at the service boundary. An empty scope remains unrestricted for existing workers until an administrator configures it.
- The administrative dashboard summarizes active operational employees and open/overdue staff tasks. The staff directory displays each employee's open-task count through an aggregate query without selecting task descriptions or notes.

## Database application

Migration `2026_09_14_000026_add_account_status_to_users.php` passed `migrate → rollback → migrate` on a copy of the working SQLite database. Integrity and foreign-key checks passed. It was then applied to the working database after this backup:

`database/backups/database-before-account-status-20260914.sqlite`

SHA-256:

`32F7786384F9E6C7443DA581F07337F1F7B2AFB81BC18BC3681C5F011690C536`

All four existing accounts received the database default `active`; no role, permission, session, order, or financial record was changed by the migration.

Migration `2026_09_14_000027_create_permission_user_table.php` also passed `migrate → rollback → migrate` on a working-database copy, with clean integrity and foreign-key checks. It was applied after this second backup:

`database/backups/database-before-direct-permissions-20260914.sqlite`

SHA-256:

`41984C561BFEB28D8F12703758555C55B35825631B2FE2F56D7A96B5371D57D9`

The new pivot table contains zero rows on the working database, so no existing account received a direct permission during deployment.

Migration `2026_09_14_000028_add_login_summary_to_users.php` passed `migrate → rollback → migrate` on a database copy, then was applied after this backup:

`database/backups/database-before-login-summary-20260914.sqlite`

SHA-256:

`DD4E4D9D8B92B8471F09A1EF6CA9B47B2C309BC84224455C015867802F2B4594`

The four existing accounts started with a zero login count and no fabricated historical last-login timestamp.

Migration `2026_09_14_000029_create_staff_departments_and_add_staff_identity.php` passed `migrate → rollback → migrate` on a working-database copy with clean integrity and foreign-key checks. It was applied after this backup:

`database/backups/database-before-staff-departments-20260914.sqlite`

SHA-256:

`AB144D6EC4790C96774C1C3653F0D2984171D40AFCDF3A5643641DE690E45925`

Two existing operational accounts received deterministic employee numbers. Customer-only and merchant-only accounts were not assigned employee numbers. No department was fabricated, and no role or permission changed.

Migration `2026_09_14_000030_create_staff_tasks_table.php` passed copied-database apply/rollback/apply and was applied after backup `database/backups/database-before-staff-tasks-20260914.sqlite` (SHA-256 `10B022F53082EDE5CF42CFA55A3C665D4B81DE1E9003F4F36B1307F5F20C6FDE`). The working task table started empty.

Migration `2026_09_14_000031_add_staff_task_self_service_permissions.php` also passed copied-database apply/rollback/apply with clean integrity and foreign keys. It was applied after backup `database/backups/database-before-staff-task-self-service-20260914.sqlite` (SHA-256 `F98846FAD6FDF79C492143753DF9756A6DB8E8799A03930C60D2AEB191028B52`). It added two own-task permissions to the administrator and delivery-worker roles and a task version counter.

Migration `2026_09_14_000032_create_staff_notes_table.php` passed the same copied-database cycle and was applied after backup `database/backups/database-before-staff-notes-20260914.sqlite` (SHA-256 `5B56446077B13C5D41F4CB4F6DF9AD48E6EAC7E1F75A02724C4C837726434001`). It added two administrator-only note permissions; the working notes table started empty.

Migration `2026_09_14_000033_add_managed_department_permission.php` passed copied-database apply/rollback/apply and was applied after backup `database/backups/database-before-managed-department-scope-20260914.sqlite` (SHA-256 `E88BA8665849E1BEC78799183335BACE0A4A12EB2C24A037B6E3C6F40E69AFC6`). The permission is available for explicit grants and is not implicitly granted to delivery workers or custom roles.

Migration `2026_09_14_000034_create_staff_location_scope_and_schedules.php` passed copied-database apply/rollback/apply with clean integrity and foreign keys. It was applied after backup `database/backups/database-before-staff-scope-schedule-20260914.sqlite` (SHA-256 `55F5514EF0EB0C0545B954DD260926C8ABC605F16F595DB4801A9A91D712C62A`). Existing workers received no fabricated location scope or schedule rows.

Migration `2026_09_14_000035_add_password_changed_at_to_users.php` passed copied-database apply/rollback/apply with clean integrity and foreign keys. It was applied after backup `database/backups/database-before-password-change-summary-20260915.sqlite` (SHA-256 `86F568F7104A23DB551EDB640C4A649A81DD405EBD35DAD5EE590DF020C17B78`). All four existing accounts retained a null password-change time; the value is populated only by a new registration or successful password-setting event.

Migration `2026_09_15_000036_add_failed_login_summary_to_users.php` passed copied-database apply/rollback/apply with clean integrity and foreign keys. It was applied after backup `database/backups/database-before-failed-login-summary-20260915.sqlite` (SHA-256 `8787031C6908699DF7CBC0C62146AF533486F26F13904A9169C787088D6271BA`). Existing accounts started with zero failures and no fabricated failed-login timestamp.

Migration `2026_09_15_000037_index_managed_staff_department_lookups.php` passed copied-database apply/rollback/apply with clean integrity and foreign keys. It was applied after backup `database/backups/database-before-managed-indexes-20260915.sqlite` (SHA-256 `AE5B0156A10A0D74A4BB3CE5554D5727F7E7B15E8B2C9A985FAC202F600447D9`). It adds lookup indexes for department manager and employee department without changing account or department records.

## Verification and remaining work

- Complete suite: **403 tests, 5421 assertions**.
- Blade compilation passed. There are **168 routes**, with no duplicate route names or method/URI signatures. Working SQLite integrity is `ok` with zero foreign-key violations.
- Two-factor authentication is not implemented yet. Login telemetry deliberately excludes IP addresses and device fingerprints. Delivery performance currently uses persisted delivery milestones only; fabricated SLA or arrival metrics are deliberately excluded.
- Production still requires a real database/queue concurrency exercise, HTTPS/session verification, monitored workers, and a tested off-machine backup restore.

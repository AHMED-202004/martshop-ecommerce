# Working database migration — 2026-09-13

The SQLite working database was backed up before any schema change to:

`database/backups/database-before-integrity-migrations-20260913.sqlite`

Backup SHA-256: `684E4C71AAC8F10DDEF37AA7F9CE3BA2B127967E179B276617EBD79708FAD79D`.

The backup is intentionally excluded from Git. Preserve it outside the application directory before any deployment or machine cleanup.

## Applied migrations

- `2026_09_08_000021_add_integrity_hash_to_merchant_documents`
- `2026_09_09_000022_add_integrity_metadata_to_product_change_requests`
- `2026_09_09_000023_add_submission_image_integrity_to_products`
- `2026_09_10_000024_add_unique_login_phone_to_users`

Before applying them, the full sequence was tested on a temporary copy as `migrate → rollback → migrate`, with zero foreign-key violations. The unique-phone migration was made idempotent because the working SQLite schema already contained the required unique index even though the migration row was pending.

## Integrity backfill

`php artisan merchant-documents:backfill-integrity` reported three eligible and zero invalid historical files. After the dry run, `--apply` updated exactly three SHA-256 values and wrote three `merchant.document_integrity_backfilled` audit records. A second dry run reported zero remaining rows; foreign-key validation remained clean.

The maintenance command defaults to dry-run, accepts `--apply` explicitly, validates local UUID paths, containment, file size and SHA-256, and rejects invalid rows with a failed exit status.

## Scheduler queue indexes

Before applying `2026_09_13_000025_add_scheduler_queue_indexes`, the current database was backed up to:

`database/backups/database-before-scheduler-indexes-20260913.sqlite`

Backup SHA-256: `EC738F3F6313090B3AE1E78B2072EE55DA607CC1C9D3D1CC15AB42E46E1C5ECE`.

The migration passed `migrate → rollback → migrate` on a separate copy before it was applied to the working database. SQLite now selects `stock_reservations_release_queue_index` and `deliveries_settlement_queue_index` for the two scheduled queue scans. The final database integrity and foreign-key checks passed, and no migrations remain pending.

The preflight copy `database/backups/migration-preflight-000025.sqlite` also remains in the Git-ignored backup directory because the execution safety boundary refused its deletion. It contains the successfully migrated test copy and should be removed manually after external backup verification if it is no longer needed.

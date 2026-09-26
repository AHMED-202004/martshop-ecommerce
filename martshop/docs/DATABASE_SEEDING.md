# Database seeding boundaries

`DatabaseSeeder` contains only repeatable marketplace foundation data: roles and permissions, supported locations, and the current category tree. It does not create users, credentials, products, offers, orders, payments, or other transactional records.

The legacy catalogue is demo data. For a disposable local database only, it can be imported explicitly with:

```text
php artisan db:seed --class=Database\Seeders\DemoDatabaseSeeder
```

Do not run the demo seeder against production or a database containing curated catalogue data. The ordinary `php artisan db:seed` path intentionally does not call it.

The storefront's old in-controller fallback is separately fail-closed through `LEGACY_CATALOG_FALLBACK_ENABLED=false`, which is the default. Set it to `true` only while exercising the old catalogue in disposable local development. Explicit demo importing remains available regardless of the display fallback so the migration command can be tested without exposing demo records automatically.

Tests use SQLite `:memory:` and may invoke individual seeders directly to verify import compatibility. No seeder was run against the working database while establishing this boundary.

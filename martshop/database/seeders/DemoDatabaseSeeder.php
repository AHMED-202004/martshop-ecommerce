<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Explicit local/demo catalogue import.
 *
 * Never include this seeder in DatabaseSeeder: its records are not curated
 * production inventory. Run it by class only in a disposable local database.
 */
class DemoDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DatabaseSeeder::class,
            LegacyCatalogSeeder::class,
        ]);
    }
}

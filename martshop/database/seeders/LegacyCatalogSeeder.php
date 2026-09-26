<?php

namespace Database\Seeders;

use App\Http\Controllers\CategoryController;
use App\Services\LegacyCatalogImporter;
use Illuminate\Database\Seeder;

class LegacyCatalogSeeder extends Seeder
{
    public function run(LegacyCatalogImporter $importer): void
    {
        $importer->import(app(CategoryController::class)->legacyProductCatalogue());
    }
}

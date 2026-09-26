<?php

namespace Database\Seeders;

use App\Http\Controllers\CategoryController;
use App\Services\CategoryTreeImporter;
use Illuminate\Database\Seeder;

class CategoryTreeSeeder extends Seeder
{
    public function run(CategoryTreeImporter $importer): void
    {
        $importer->import(app(CategoryController::class)->legacyTree());
    }
}

<?php

use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\PublicSettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->middleware('throttle:public-api')->group(function () {
    Route::get('settings', PublicSettingsController::class)->name('settings');
    Route::get('categories', [CatalogController::class, 'categories'])->name('categories.index');
    Route::get('products', [CatalogController::class, 'index'])->name('products.index');
    Route::get('products/{slug}', [CatalogController::class, 'show'])
        ->where('slug', '[A-Za-z0-9][A-Za-z0-9_-]{0,199}')
        ->name('products.show');
});

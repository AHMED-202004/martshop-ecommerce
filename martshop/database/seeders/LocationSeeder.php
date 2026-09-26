<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            ['name' => 'شمال غزة', 'slug' => 'north-gaza', 'type' => 'governorate', 'sort_order' => 10],
            ['name' => 'غزة', 'slug' => 'gaza', 'type' => 'governorate', 'sort_order' => 20],
            ['name' => 'دير البلح', 'slug' => 'deir-al-balah', 'type' => 'governorate', 'sort_order' => 30],
            ['name' => 'خان يونس', 'slug' => 'khan-younis', 'type' => 'governorate', 'sort_order' => 40],
            ['name' => 'رفح', 'slug' => 'rafah', 'type' => 'governorate', 'sort_order' => 50],
        ];

        foreach ($locations as $location) {
            Location::updateOrCreate(['slug' => $location['slug']], $location + ['is_active' => true]);
        }
    }
}

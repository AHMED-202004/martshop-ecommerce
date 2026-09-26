<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Brand;

class MoreBrandsSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            ['slug' => 'adidas',        'name' => 'Adidas'],
            ['slug' => 'skechers',      'name' => 'Skechers'],
            ['slug' => 'reebok',        'name' => 'Reebok'],
            ['slug' => 'under-armour',  'name' => 'Under Armour'],
            ['slug' => 'hi-tec',        'name' => 'HI-TEC'],
            ['slug' => 'somi',          'name' => 'SOMI'],
            ['slug' => 'diadora',       'name' => 'Diadora'],
            ['slug' => 'rock',          'name' => 'ROCK'],
            ['slug' => 'rue-broca',     'name' => 'RUE BROCA'],
            // لو حاب تضيف كمان:
            ['slug' => 'puma',          'name' => 'PUMA'],
            ['slug' => 'dr-flexer',     'name' => 'Dr. Flexer'],
            ['slug' => 'hush-puppies',  'name' => 'Hush Puppies'],
            ['slug' => 'stationery',    'name' => 'Stationery'],
        ];

        foreach ($brands as $b) {
            Brand::firstOrCreate(['slug' => $b['slug']], ['name' => $b['name']]);
        }
    }
}

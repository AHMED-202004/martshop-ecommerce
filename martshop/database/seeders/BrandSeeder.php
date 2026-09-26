<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Brand;

class BrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $names = ['Adidas', 'Reebok', 'Hush Puppies', 'Skechers', 'Rock', 'Dr.Flexer'];

        // نمنع التكرار عبر slug، ونحدّث الاسم إن وُجد
        foreach ($names as $name) {
            Brand::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            );
             $brands = [
            ['name' => 'Skechers',  'slug' => 'skechers'],
            ['name' => 'Stationery','slug' => 'stationery'],
            // ... أي براندات ثانية عندك
        ];

        foreach ($brands as $b) {
            Brand::updateOrCreate(
                ['name' => trim($b['name'])], // مفتاح فريد
                ['slug' => $b['slug']]
            );
        }
    }
}
}
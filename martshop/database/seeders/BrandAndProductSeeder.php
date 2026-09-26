<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Support\DetectBrand;

class BrandAndProductSeeder extends Seeder
{
    public function run(): void
    {
        // 1) أنشئ/حدّث الماركات الأساسية (عدّل القائمة لو حبيت)
        $brands = [
            'adidas', 'Skechers', 'Reebok', 'PUMA', 'Under Armour',
            'HI-TEC', 'Diadora', 'Rock', 'RUE BROCA', 'Abdan', 'BLX', 'Sami Boutique', 'Generic',
        ];
        $brandIds = [];
        foreach ($brands as $name) {
            $brand = Brand::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            );
            $brandIds[$name] = $brand->id;
        }

        // 2) اجلب بيانات المنتجات من ملفك (نفس التنسيق الموجود عندك)
        // ضعه مثلاً في resources/data/products.php ويعمل return لمصفوفة كبيرة
        // بالشكل ['category/..' => [[ 'name'=>.., 'slug'=>.., 'price'=>.., 'image'=>.., 'sizes'=>[] ], ...]]
        $data = require base_path('resources/data/products.php');

        // 3) فرّغ المصفوفة حسب الأقسام إلى قائمة واحدة
        $flat = [];
        foreach ($data as $section => $items) {
            foreach ($items as $it) {
                $flat[] = $it + ['section' => $section];
            }
        }

        // 4) أنشئ/حدّث المنتجات مع ربط الماركة
        foreach ($flat as $it) {
            $name = (string)($it['name'] ?? '');
            $slug = (string)($it['slug'] ?? Str::slug($name));
            $brandName = $it['brand'] ?? DetectBrand::from($name, $slug) ?? 'Generic';
            $brandId   = $brandIds[$brandName] ?? $brandIds['Generic'];

            /** @var \App\Models\Product $p */
            $p = Product::updateOrCreate(
                ['slug' => $slug],
                [
                    'name'       => $name,
                    'price'      => (float)($it['price'] ?? 0),
                    'sale_price' => (float)($it['old_price'] ?? $it['sale_price'] ?? 0),
                    'image'      => $it['image'] ?? null,
                    'brand_id'   => $brandId,
                ]
            );

            // إن كان عندك جدول للقياسات/الخيارات (variants) عالجها هنا…
            // مثال بسيط لحفظ أحجام رقمية/حروفية لو أردت
            // $p->syncSizes($it['sizes'] ?? []);
        }
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductVariant;

class KidsAndNewArrivalsSeeder extends Seeder
{
    public function run(): void
    {
        
        // الماركات
        $brandIds = [];
        foreach ([
            ['slug' => 'skechers',     'name' => 'Skechers'],
            ['slug' => 'reebok',       'name' => 'Reebok'],
            ['slug' => 'hush-puppies', 'name' => 'Hush Puppies'],
            ['slug' => 'dr-flexer',    'name' => 'Dr. Flexer'],
            ['slug' => 'puma',         'name' => 'PUMA'],
            ['slug' => 'stationery',   'name' => 'Stationery'],
        ] as $b) {
            $brand = Brand::firstOrCreate(['slug' => $b['slug']], ['name' => $b['name']]);
            $brandIds[$b['slug']] = $brand->id;
        }

        // المنتجات (وصلنا حديثًا)
        $items = [
            // حقيبة بُوما Phase (كان عندك ماركة PUMA بس محسوبة على Reebok بالخطأ)
            [
                'slug'   => 'puma-phase-backpack',
                'name'   => 'حقيبة ظهر بُوما Phase',
                'brand'  => 'puma',
                'price'  => 120,
                'sale'   => 110.99,
                'image'  => 'assets/img/products/new/phase-backpack.jpg',
                'variants' => [
                    ['size_type' => 'alpha', 'size_value' => 'One Size', 'color' => 'Black', 'stock' => 20],
                ],
            ],

            [
                'slug'   => 'hush-puppies-casual-shoe',
                'name'   => 'حذاء كاجوال رجالي بدون رباط - Hush Puppies',
                'brand'  => 'hush-puppies',
                'price'  => 320,
                'sale'   => 149.99,
                'image'  => 'assets/img/products/new/حذاء-كاجوال-طبي-جلد-بدون-رباط-للرجال-لون-أسود.jpg',
                'variants' => [
                    ['size_type' => 'num', 'size_value' => 39, 'color' => 'Black', 'stock' => 6],
                    ['size_type' => 'num', 'size_value' => 40, 'color' => 'Black', 'stock' => 6],
                    ['size_type' => 'num', 'size_value' => 41, 'color' => 'Black', 'stock' => 6],
                ],
            ],
            [
                'slug'   => 'dr-flexer-medic-men-leather-shoe',
                'name'   => 'حذاء جلدي طبي للرجال - Dr. Flexer',
                'brand'  => 'dr-flexer',
                'price'  => 320,
                'sale'   => 149.99,
                'image'  => 'assets/img/products/new/حذاء-كاجوال-طبي-جلد-بدون-رباط-للرجال-لون-أسود (1).jpg',
                'variants' => [
                    ['size_type' => 'num', 'size_value' => 39, 'color' => 'Black', 'stock' => 6],
                    ['size_type' => 'num', 'size_value' => 41, 'color' => 'Black', 'stock' => 6],
                ],
            ],
            [
                'slug'   => 'skechers-dyna-lite',
                'name'   => 'سكيتشرز دَينا لايت',
                'brand'  => 'skechers',
                'price'  => 150,
                'sale'   => 99.99,
                'image'  => 'assets/img/products/new/skechers-dyna-lite.jpg',
                'variants' => [
                    ['size_type' => 'num', 'size_value' => 29, 'color' => 'Grey', 'stock' => 6],
                    ['size_type' => 'num', 'size_value' => 30, 'color' => 'Grey', 'stock' => 6],
                    ['size_type' => 'num', 'size_value' => 31, 'color' => 'Grey', 'stock' => 6],
                    ['size_type' => 'num', 'size_value' => 32, 'color' => 'Grey', 'stock' => 6],
                    ['size_type' => 'num', 'size_value' => 33, 'color' => 'Grey', 'stock' => 6],
                ],
            ],

            // شنط سكيتشرز
            [
                'slug'  => 'skechers-unisex-backpack-navy',
                'name'  => 'حقيبة ظهر سكيتشرز - كحلي',
                'brand' => 'skechers', 'price' => 100, 'sale' => 89.99,
                'image' => 'assets/img/products/new/skechers-unisex-backpack-navy.jpg',
                'variants' => [['size_type' => 'alpha', 'size_value' => 'One Size', 'color' => 'Navy', 'stock' => 25]],
            ],
            [
                'slug'  => 'skechers-unisex-backpack-black',
                'name'  => 'حقيبة ظهر سكيتشرز - أسود',
                'brand' => 'skechers', 'price' => 100, 'sale' => 89.99,
                'image' => 'assets/img/products/new/skechers-unisex-backpack-black.jpg',
                'variants' => [['size_type' => 'alpha', 'size_value' => 'One Size', 'color' => 'Black', 'stock' => 25]],
            ],
            [
                'slug'  => 'skechers-unisex-backpack-white',
                'name'  => 'حقيبة ظهر سكيتشرز - أبيض',
                'brand' => 'skechers', 'price' => 130, 'sale' => 99.99,
                'image' => 'assets/img/products/new/skechers-unisex-backpack-white.jpg',
                'variants' => [['size_type' => 'alpha', 'size_value' => 'One Size', 'color' => 'White', 'stock' => 25]],
            ],
            [
                'slug'  => 'skechers-unisex-backpack-grey',
                'name'  => 'حقيبة ظهر سكيتشرز - رمادي',
                'brand' => 'skechers', 'price' => 130, 'sale' => 99.99,
                'image' => 'assets/img/products/new/skechers-unisex-backpack-grey.jpg',
                'variants' => [['size_type' => 'alpha', 'size_value' => 'One Size', 'color' => 'Grey', 'stock' => 25]],
            ],
            [
                'slug'  => 'skechers-unisex-backpack-pink',
                'name'  => 'حقيبة ظهر سكيتشرز - زهري',
                'brand' => 'skechers', 'price' => 130, 'sale' => 99.99,
                'image' => 'assets/img/products/new/skechers-unisex-backpack-pink.jpg',
                'variants' => [['size_type' => 'alpha', 'size_value' => 'One Size', 'color' => 'Pink', 'stock' => 25]],
            ],

            // سكيتشرز رجالي
            [
                'slug'  => 'skechers-men-summits-sport-shoes',
                'name'  => 'حذاء سكيتشرز سمايتس للرجال - رياضي',
                'brand' => 'skechers', 'price' => 260, 'sale' => 147.99,
                'image' => 'assets/img/products/new/skechers-men-s-track-leshur-shoes.jpg',
                'variants' => [
                    ['size_type' => 'num', 'size_value' => 44, 'color' => 'Black', 'stock' => 8],
                    ['size_type' => 'num', 'size_value' => 45, 'color' => 'Black', 'stock' => 8],
                    ['size_type' => 'num', 'size_value' => 46, 'color' => 'Black', 'stock' => 8],
                ],
            ],
            [
                'slug'  => 'skechers-men-track-glendor',
                'name'  => 'سكيتشرز تراك - غليندور (رجالي)',
                'brand' => 'skechers', 'price' => 260, 'sale' => 147.99,
                'image' => 'assets/img/products/new/skechers-men-s-track-glendor-shoes.jpg',
                'variants' => [
                    ['size_type' => 'num', 'size_value' => 44, 'color' => 'Grey', 'stock' => 8],
                ],
            ],
            [
                'slug'  => 'skechers-men-track-leshur',
                'name'  => 'سكيتشرز تراك - ليشور (رجالي) - أسود',
                'brand' => 'skechers', 'price' => 260, 'sale' => 147.99,
                'image' => 'assets/img/products/new/حذاء-سبورت-للنساء-لون-أسود.jpg',
                'variants' => [
                    ['size_type' => 'num', 'size_value' => 44, 'color' => 'Black', 'stock' => 8],
                ],
            ],

            // قرطاسية/أطفال (انتبه لتمييز الـ slug)
            [
                'slug'  => 'stationery-10in1-multicolor-pen-a',
                'name'  => 'قلم حبر متعدد الرؤوس 10 لون',
                'brand' => 'stationery', 'price' => 10, 'sale' => 7.99,
                'image' => 'assets/img/products/new/قلم-حبر-متعدد-الرؤوس-ب-10-ألوان.jpg',
                'variants' => [['size_type' => 'alpha', 'size_value' => 'One Size', 'color' => 'Multi', 'stock' => 100]],
            ],
            [
                'slug'  => 'stationery-10in1-multicolor-pen-b',
                'name'  => 'قلم حبر متعدد الرؤوس 10 لون',
                'brand' => 'stationery', 'price' => 10, 'sale' => 7.99,
                'image' => 'assets/img/products/new/قلم-حبر-متعدد-الرؤوس-ب-10-ألوان (1).jpg',
                'variants' => [['size_type' => 'alpha', 'size_value' => 'One Size', 'color' => 'Multi', 'stock' => 100]],
            ],
            [
                'slug'  => 'stationery-10in1-multicolor-pen-c',
                'name'  => 'قلم حبر متعدد الرؤوس 10 لون',
                'brand' => 'stationery', 'price' => 10, 'sale' => 7.99,
                'image' => 'assets/img/products/new/قلم-حبر-متعدد-الرؤوس-ب-10-ألوان (2).jpg',
                'variants' => [['size_type' => 'alpha', 'size_value' => 'One Size', 'color' => 'Multi', 'stock' => 100]],
            ],
            [
                'slug'  => 'stationery-cute-slider-eraser',
                'name'  => 'محاية أشكال درج سحب',
                'brand' => 'stationery', 'price' => 7, 'sale' => 4.99,
                'image' => 'assets/img/products/new/محايه-اشكال-دزني-سحب.jpg',
                'variants' => [['size_type' => 'alpha', 'size_value' => 'One Size', 'color' => 'Assorted', 'stock' => 100]],
            ],
            [
                'slug'  => 'stationery-pen-with-charm-stitch',
                'name'  => 'قلم جير مزدان مدالية شكل ستيتش',
                'brand' => 'stationery', 'price' => 7, 'sale' => 4.99,
                'image' => 'assets/img/products/new/قلم-حبر-دزني-مدالية-شكل-ستتش.jpg',
                'variants' => [['size_type' => 'alpha', 'size_value' => 'One Size', 'color' => 'Pink', 'stock' => 100]],
            ],
            [
                'slug'  => 'stationery-pen-with-charm-mario',
                'name'  => 'قلم جير مزدان مدالية شكل ماريو',
                'brand' => 'stationery', 'price' => 7, 'sale' => 4.99,
                'image' => 'assets/img/products/new/قلم-حبر-دزني-مدالية-شكل-ماريو.jpg',
                'variants' => [['size_type' => 'alpha', 'size_value' => 'One Size', 'color' => 'Blue', 'stock' => 100]],
            ],
            [
                'slug'  => 'stationery-pencil-case-stitch',
                'name'  => 'مقلمة دزني جديد مع قرطاسيه 6 قطع',
                'brand' => 'stationery', 'price' => 18, 'sale' => 7.00,
                'image' => 'assets/img/products/new/مقلمه-دزني-حديد-مع-قرطاسيه-6-قطع.jpg',
                'variants' => [['size_type' => 'alpha', 'size_value' => 'One Size', 'color' => 'Blue', 'stock' => 50]],
            ],
            [
                'slug'  => 'stationery-kids-set-hello-kitty-6pc',
                'name'  => 'كرت قرطاسيه دزني مع 6 قطع',
                'brand' => 'stationery', 'price' => 18, 'sale' => 7.99,
                'image' => 'assets/img/products/new/كرت-قرطاسيه-دزني-مع-ساعه-6-قطع.jpg',
                'variants' => [['size_type' => 'alpha', 'size_value' => 'One Size', 'color' => 'Multi', 'stock' => 50]],
            ],
            [
                'slug'  => 'stationery-kids-set-7pc',
                'name'  => 'كرت قرطاسيه دزني مع دزنان 7 قطع',
                'brand' => 'stationery', 'price' => 22, 'sale' => 10.00,
                'image' => 'assets/img/products/new/كرت-قرطاسيه-دزني-مع-دزدان-7-قطع.jpg',
                'variants' => [['size_type' => 'alpha', 'size_value' => 'One Size', 'color' => 'Multi', 'stock' => 50]],
            ],
        ];

        foreach ($items as $it) {
            $product = Product::updateOrCreate(
                ['slug' => $it['slug']],
                [
                    'name'         => $it['name'],
                    'brand_id'     => $brandIds[$it['brand']] ?? null,
                    'price'        => $it['price'],
                    'sale_price'   => $it['sale'],
                    'image'        => $it['image'],
                    'status'       => 'active',
                    'is_super_deal'=> false,
                    'is_new'       => true,
                ]
            );

            if (!empty($it['variants'])) {
                foreach ($it['variants'] as $v) {
                    ProductVariant::updateOrCreate(
                        [
                            'product_id' => $product->id,
                            'size_type'  => $v['size_type'],
                            'size_value' => (string)$v['size_value'],
                            'color'      => $v['color'] ?? null,
                        ],
                        ['stock' => $v['stock'] ?? 0]
                    );
                }
            }
        }
    }
}

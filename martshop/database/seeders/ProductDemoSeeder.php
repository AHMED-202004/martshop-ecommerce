<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductVariant;

class ProductDemoSeeder extends Seeder
{
    public function run(): void
    {
        // تأكد أن الماركات موجودة
        $brandIds = [];
        foreach (['OSA','Reebok','Skechers','Hush Puppies','Dr.Flexer','Rock'] as $b) {
            $brandIds[$b] = Brand::firstOrCreate(['name'=>$b], ['slug'=>Str::slug($b)])->id;
        }

        // دالة صغيرة للإضافة بسرعة
        $add = function(array $p, array $variants = []) use ($brandIds) {
            $p['slug']         = Str::slug($p['name']).'-'.Str::random(5);
            $p['brand_id']     = $brandIds[$p['brand']] ?? null;
            $p['status']       = $p['status'] ?? 'active';
            $p['is_super_deal']= true;

            /** @var \App\Models\Product $prod */
            $prod = Product::create([
                'slug'        => $p['slug'],
                'name'        => $p['name'],
                'brand_id'    => $p['brand_id'],
                'price'       => $p['price'],
                'sale_price'  => $p['sale_price'],
                'image'       => $p['image'], // ضع الصور في public/assets/products/...
                'status'      => $p['status'],
                'is_super_deal'=> true,
            ]);

            foreach ($variants as $v) {
                ProductVariant::create([
                    'product_id' => $prod->id,
                    'size_type'  => $v['type'],     // alpha | num
                    'size_value' => (string)$v['value'],
                    'color'      => $v['color'] ?? 20,
                    'stock'      => $v['stock'] ?? 10,
                ]);
            }
        };

        // =============== منتجات من الصور التي أرسلتها ===============
        // (تأكّد من وجود الصور في public/assets/products/ بنفس الأسماء)
        $add([
            'name'  => 'تيشرت شبابي اوفر سايز قطن لون أحمر من OSA',
            'brand' => 'OSA',
            'price' => 80,
            'sale_price' => 15.99,
            'image' => 'assets/products/تيشيريت-شبابي-اوفر-سايز-قطن-لون-احمر-من-osa.jpg',
        ], array_map(fn($s)=>['type'=>'alpha','value'=>$s], ['XL','L','M','S']));

        $add([
            'name'  => 'بلوزة شبابية جولف قطن بني',
            'brand' => 'OSA',
            'price' => 30,
            'sale_price' => 15.99,
            'image' => 'assets/products/بلوزة-شبابية-جولف-قطن-بني.jpg',
        ], [['type'=>'alpha','color','value'=>'One Size']]);

        $add([
            'name'  => 'بلوزة شبابية جولف قطن اسود',
            'brand' => 'OSA',
            'price' => 80,
            'sale_price' => 15.99,
            'image' => 'assets/products/تيشيريت-شبابي-اوفر-سايز-قطن-لون-اسود-من-osa.jpg',
        ], [['type'=>'alpha','color','value'=>'One Size']]);

          $add([
            'name'  => 'بلوزة شبابية جولف قطن زيتي',
            'brand' => 'OSA',
            'price' => 80,
            'sale_price' => 15.99,
            'image' => 'assets/products/تيشيريت-شبابي-اوفر-سايز-قطن-لون-زيتي-من-osa.jpg',
        ], [['type'=>'alpha','color','value'=>'One Size']]);

        $add([
            'name'  => 'Reebok Unisex United By Fitness (UBF) Baseball Cap',
            'brand' => 'Reebok',
            'price' => 60,
            'sale_price' => 39.99,
            'image' => 'assets/products/reebok-ubf-baseb-cap.jpg',
        ]);

        $add([
            'name'  => 'بنطال صيفي للسيدات لون بيج',
            'brand' => 'OSA',
            'price' => 30,
            'sale_price' => 7.99,
            'image' => 'assets/products/بنطال-صيفي-للنساء-لون-بيج.jpg',
        ], array_map(fn($s)=>['type'=>'alpha','color','value'=>$s], ['XL','L','M']));

        $add([
            'name'  => 'HUSH PUPPIES Casual Shoe',
            'brand' => 'Hush Puppies',
            'price' => 420,
            'sale_price' => 319.99,
            'image' => 'assets/products/hush-puppies-casual-shoe.jpg',
        ], array_map(fn($n)=>['type'=>'num','color','value'=>$n], [46,45,44,43,42,41]));

        $add([
            'name'  => 'SKECHERS Dyna Lite',
            'brand' => 'Skechers',
            'price' => 150,
            'sale_price' => 99.99,
            'image' => 'assets/products/skechers-dyna-lite.jpg',
        ], array_map(fn($n)=>['type'=>'num','color','value'=>$n], [33,32,31,30,29]));

        $add([
            'name'  => 'Dr. Flexer Medic Men Leather Shoe',
            'brand' => 'Dr.Flexer',
            'price' => 250,
            'sale_price' => 149.99,
            'image' => 'assets/products/drflexer-medic-men-shoe.jpg',
        ], array_map(fn($n)=>['type'=>'num','color','value'=>$n], [41,39]));

        $add([
            'name'  => 'Hush Puppies Men\'s Casual Slip On',
            'brand' => 'Hush Puppies',
            'price' => 450,
            'sale_price' => 299.99,
            'image' => 'assets/products/hush-puppies-casual-shoe (1).jpg',
        ], [['type'=>'num','color','value'=>40]]);

        $add([
            'name'  => 'Reebok Unisex Training Essentials Grip Bag',
            'brand' => 'Reebok',
            'price' => 140,
            'sale_price' => 99.99,
            'image' => 'assets/products/reebok-te-s-grip.jpg',
        ]);
      $add([
    'name'  => 'بنطلون رجالي كاجوال جيب I CAN — لون كحلي',
    'brand' => 'Generic',
    'price' => 70.00,
    'sale_price' => 32.99,
    'image' => 'assets/img/demo/men-cargo-ican-navy.jpg',
]);

$add([
    'name'  => 'بنطلون رجالي كاجوال جيب I CAN — لون رصاصي فاتح',
    'brand' => 'Generic',
    'price' => 70.00,
    'sale_price' => 32.99,
    'image' => 'assets/img/demo/men-cargo-ican-light-grey.jpg',
]);

$add([
    'name'  => 'بنطلون رجالي كاجوال جيب I CAN — لون زيتي (موديل شعار)',
    'brand' => 'Generic',
    'price' => 70.00,
    'sale_price' => 32.99,
    'image' => 'assets/img/demo/men-cargo-ican-olive-logo.jpg',
]);

$add([
    'name'  => 'جينز موديل بوي فِرند سِرج عالي — بدون ليكار (أزرق غامق)',
    'brand' => 'Generic',
    'price' => 150.00,
    'sale_price' => 99.99,
    'image' => 'assets/img/demo/men-jeans-boyfriend-darkblue.jpg',
]);

$add([
    'name'  => 'جينز موديل بوي فِرند سِرج عالي — بدون ليكار (أزرق فاتح)',
    'brand' => 'Generic',
    'price' => 150.00,
    'sale_price' => 99.99,
    'image' => 'assets/img/demo/men-jeans-boyfriend-lightblue.jpg',
]);

$add([
    'name'  => 'جينز موديل بوي فِرند سِرج عالي — بدون ليكار (كحلي)',
    'brand' => 'Generic',
    'price' => 150.00,
    'sale_price' => 99.99,
    'image' => 'assets/img/demo/men-jeans-boyfriend-navy.jpg',
]);

$add([
    'name'  => "Skechers Women's Ultra Flex - Harmonious Shoes (Black)",
    'brand' => 'Skechers',
    'price' => 300.00,
    'sale_price' => 259.99,
    'image' => 'assets/img/demo/women-skechers-ultra-flex-black.jpg',
]);

$add([
    'name'  => "Skechers Women's Ultra Flex - Harmonious Shoes (White)",
    'brand' => 'Skechers',
    'price' => 300.00,
    'sale_price' => 259.99,
    'image' => 'assets/img/demo/women-skechers-ultra-flex-white.jpg',
]);

$add([
    'name'  => 'Reebok Royal Complete – White/Green',
    'brand' => 'Reebok',
    'price' => 400.00,
    'sale_price' => 199.99,
    'image' => 'assets/img/demo/women-reebok-royal-complete-white-green.jpg',
]);

$add([
    'name'  => 'Skechers Smooth Street - Steady-Zip Shoes - White',
    'brand' => 'Skechers',
    'price' => 200.00,
    'sale_price' => 149.99,
    'image' => 'assets/img/demo/women-skechers-smooth-street-white.jpg',
]);

$add([
    'name'  => 'حذاء يومي مريح نسائي - بيج',
    'brand' => 'Generic',
    'price' => 80.00,
    'sale_price' => 32.99,
    'image' => 'assets/img/demo/women-daily-slipon-beige.jpg',
]);
$add([
    'name'  => 'علبة أقلام جاف مشّك أزرق Pensan موديل 2021 (50 قلم)',
    'brand' => 'Pensan',
    'price' => 40.00,
    'sale_price' => 19.99,
    'image' => 'assets/img/demo/st-pensan-2021-blue-50.jpg',
]);

$add([
    'name'  => 'ربطة دفاتر مدرسية عربي 40 ورقة (20 دفتر)',
    'brand' => 'Generic',
    'price' => 20.00,
    'sale_price' => 8.99,
    'image' => 'assets/img/demo/st-notebooks-40-20.jpg',
]);


    }
}

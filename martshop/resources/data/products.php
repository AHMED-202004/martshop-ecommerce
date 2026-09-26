<?php
/**
 * ارجع مصفوفة منتجات منظّمة على أقسام (الأقسام اختيارية – الأهم شكل العنصر).
 * كل عنصر يدعم الحقول: name, slug, brand, price, old_price, image, sizes[]
 */
return [

    // أمثلة – بدّلها/كبّرها بقائمتك الكاملة
    'deals' => [
        
         
       
        [
            'name'      => 'Reebok Royal Complete – White/Green',
            'slug'      => 'w-reebok-royal-complete-white-green',
            'brand'     => 'Reebok',
            'price'     => 400,
            'old_price' => 199.99,
            'image'     => '/assets/img/demo/women-reebok-royal-complete-white-green.jpg',
            'sizes'     => ['35','35.5','36','36.5','37','37.5','38','39','40','40.5'],
        ],
       
      
    ],

    // تقدر تضيف أقسام أخرى أو تنسخ بقيّة قائمتك هنا…
    'home-fragrance' => [
        [
            'name'      => 'معطر جو عِبدان برائحة الياسمين - حجم 25 مل',
            'slug'      => 'room-freshener-abdan-jasmine-25ml',
            'brand'     => 'Abdan',
            'price'     => 9,
            'old_price' => 5.99,
            'image'     => '/assets/img/demo/abdan-jasmine-25.jpg',
            'sizes'     => [],
        ],
        [
            'name'      => 'معطر جو BLX - كول اكس 250 مل',
            'slug'      => 'blx-cool-x-250ml',
            'brand'     => 'BLX',
            'price'     => 25,
            'old_price' => 19.99,
            'image'     => '/assets/img/demo/blx-coolx-250.jpg',
            'sizes'     => [],
        ],
    ],

            // رياضي
            'shoes/men/sport' => [
                [
                    'name'      => "adidas Men's Ultimashow 2.0 Shoes - Grey",
                    'slug'      => 'adidas-ultimashow-2-grey',
                    'price'     => 249.99,
                    'old_price' => 280,
                    'image'     => '/assets/img/demo/adidas-men-s-jogit-shoes-black.jpg' ?: $ph,
                    'sizes'     => ['40','41','42','43','44'],
                ],
                [
                    'name'      => "adidas Men's Jogit Shoes - White",
                    'slug'      => 'adidas-jogit-white',
                    'price'     => 249.99,
                    'old_price' => 280,
                    'image'     => '/assets/img/demo/adidas-men-s-jogit-shoes-white.jpg' ?: $ph,
                    'sizes'     => ['40','41','42','43','44','45'],
                ],
                [
                    'name'      => "adidas Men's Jogit Shoes - Grey",
                    'slug'      => 'adidas-jogit-Grey',
                    'price'     => 249.99,
                    'old_price' => 280,
                    'image'     => '/assets/img/demo/adidas-men-s-ultimashow-20-shoes-grey.jpg' ?: $ph,
                    'sizes'     => ['40','41','42','43','44','45'],
                ],
                [
                    'name'      => "حذاء كاجوال رجالي أبيض",
                    'slug'      => 'casual-white-men',
                    'price'     => 32.99,
                    'old_price' => 80,
                    'image'     => '/assets/img/demo/حذاء-رياضي-للرجال-لون-أبيض.jpg' ?: $ph,
                    'sizes'     => ['39','40','41','42','43','44','45'],
                ],
                [
                    'name'      => "حذاء كاجوال رجالي ابيض و أسود",
                    'slug'      => 'casual-black-white-men',
                    'price'     => 33.00,
                    'old_price' => 80,
                    'image'     => '/assets/img/demo/حذاء-رياضي-للرجال-لون-أبيض-و-أسود.jpg' ?: $ph,
                    'sizes'     => ['39','40','41','42','43','44','45'],
                ],
                [
                    'name'      => "حذاء رياضي محير ولادي لون كحلي ورمادي",
                    'slug'      => 'blue-sport-men',
                    'price'     => 15.99,
                    'old_price' => 70,
                    'image'     => '/assets/img/demo/حذاء-رياضي-للرجال-لون-كحلي-ورمادي.jpg' ?: $ph,
                    'sizes'     => ['37','38','39','40'],
                ],
            ],

            // رسمية
            'shoes/men/formal' => [
                ['name'=>'حذاء كلاسيك رجالي جلد أسود - سادة','slug'=>'mens-formal-black-plain','price'=>199.00,'old_price'=>240,'image'=>'/assets/img/demo/formal-black-plain.jpg','sizes'=>['40','41','42','43','44']],
                ['name'=>'حذاء رسمي جلد طبيعي بني','slug'=>'mens-formal-brown-leather','price'=>219.00,'old_price'=>260,'image'=>'/assets/img/demo/formal-brown.jpg','sizes'=>['40','41','42','43']],
                ['name'=>'لوفر رجالي كلاسيك — أسود','slug'=>'mens-loafer-classic-black','price'=>179.00,'old_price'=>200,'image'=>'/assets/img/demo/formal-loafer-black.jpg','sizes'=>['41','42','43','44']],
                ['name'=>'أوكسفورد رباط — جلد أسود لامع','slug'=>'mens-oxford-black-shiny','price'=>249.00,'old_price'=>299,'image'=>'/assets/img/demo/formal-oxford-shiny.jpg','sizes'=>['40','41','42','43','44']],
                ['name'=>'أوكسفورد رباط — جلد بني داكن','slug'=>'mens-oxford-brown','price'=>239.00,'old_price'=>280,'image'=>'/assets/img/demo/formal-oxford-brown.jpg','sizes'=>['40','41','42','43','44','45']],
                ['name'=>'حذاء مريح للعمل بطبقة مبطنة','slug'=>'mens-formal-comfy','price'=>169.00,'old_price'=>190,'image'=>'/assets/img/demo/formal-comfy.jpg','sizes'=>['40','41','42','43']],
                ['name'=>'لوفر سليب-أون بني — نعال مريح','slug'=>'mens-loafer-brown-slipon','price'=>189.00,'old_price'=>230,'image'=>'/assets/img/demo/formal-loafer-brown.jpg','sizes'=>['40','41','42','43','44']],
                ['name'=>'حذاء رسمي أسود — مقدّمة مبرومة','slug'=>'mens-formal-black-rounded','price'=>209.00,'old_price'=>250,'image'=>'/assets/img/demo/formal-black-rounded.jpg','sizes'=>['41','42','43','44']],
            ],

            // بسطار
            'shoes/men/boots' => [
                ['name'=>'بسطار للرجال لون عسلي','slug'=>'mens-boots-honey-1','price'=>119.99,'old_price'=>150,'image'=>'/assets/img/demo/boots-honey-1.jpg','sizes'=>['45','44','43','42','41','40']],
                ['name'=>'بسطار للرجال لون بني','slug'=>'mens-boots-brown-1','price'=>119.99,'old_price'=>150,'image'=>'/assets/img/demo/boots-brown-1.jpg','sizes'=>['45','44','43','42','41','40']],
                ['name'=>'بسطار للرجال لون أسود','slug'=>'mens-boots-black-1','price'=>119.99,'old_price'=>150,'image'=>'/assets/img/demo/boots-black-1.jpg','sizes'=>['45','44','43','42','41','40']],
                ['name'=>'بسطار جلد ساق طويل للرجال — لون أسود ونعل بني','slug'=>'mens-long-boot-black-brownsole','price'=>44.00,'old_price'=>100,'image'=>'/assets/img/demo/boots-long-black.jpg','sizes'=>['44','43','42','41','40']],
                ['name'=>'بسطار جلد ساق طويل للرجال — لون بني','slug'=>'mens-long-boot-brown','price'=>44.00,'old_price'=>100,'image'=>'/assets/img/demo/boots-long-brown.jpg','sizes'=>['47','46','45','44','43','42','41','40']],
                ['name'=>'بسطار للرجال لون عسلي','slug'=>'mens-boots-honey-2','price'=>119.99,'old_price'=>150,'image'=>'/assets/img/demo/boots-honey-2.jpg','sizes'=>['45','44','43','42','41','40']],
                ['name'=>'بسطار للرجال لون أسود (سليب-أون)','slug'=>'mens-boots-black-2','price'=>119.99,'old_price'=>150,'image'=>'/assets/img/demo/boots-black-2.jpg','sizes'=>['45','44','43','42','41','40']],
                ['name'=>'بسطار للرجال لون بني','slug'=>'mens-boots-brown-2','price'=>119.99,'old_price'=>150,'image'=>'/assets/img/demo/boots-brown-2.jpg','sizes'=>['45','44','43','42','41','40']],
            ],

            // كاجوال
            'shoes/men/casual' => [
                ['name'=>'حذاء كاجوال جلد موديل 5555/5N R من جولف أند هورس — لون عسلي','slug'=>'casual-5555-5n-honey','price'=>169.99,'old_price'=>220,'image'=>'/assets/img/demo/casual-5555-5n-honey.jpg','sizes'=>['46','45','44','43','42','41','40']],
                ['name'=>'حذاء كاجوال جلد موديل 5556/5N R من جولف أند هورس — لون عسلي','slug'=>'casual-5556-5n-honey','price'=>169.99,'old_price'=>220,'image'=>'/assets/img/demo/casual-5556-5n-honey.jpg','sizes'=>['46','45','44','43','42','41','40']],
                ['name'=>'حذاء كاجوال جلد موديل 88/2G R من جولف أند هورس للرجال — لون بني','slug'=>'casual-88-2g-brown','price'=>179.99,'old_price'=>250,'image'=>'/assets/img/demo/casual-88-2g-brown.jpg','sizes'=>['46','45','44','43','42','41','40']],
                ['name'=>'حذاء كاجوال شبابي لون أسود (قماش)','slug'=>'casual-young-black-canvas','price'=>15.99,'old_price'=>50,'image'=>'/assets/img/demo/casual-young-black.jpg','sizes'=>['46','45','44','43','42','41','40']],
                ['name'=>'حذاء كاجوال طبي جلد بدون رباط — لون أسود','slug'=>'casual-medical-nolace-black','price'=>222.00,'old_price'=>260,'image'=>'/assets/img/demo/casual-medical-nolace-black.jpg','sizes'=>['48','47','46','45','44','43','42','41','40']],
                ['name'=>'حذاء كاجوال للرجال لون أسود (سليب-أون)','slug'=>'casual-slipon-black','price'=>119.99,'old_price'=>150,'image'=>'/assets/img/demo/casual-slipon-black.jpg','sizes'=>['45','44','43','42','41','40']],
                ['name'=>'حذاء كاجوال للرجال لون زيتي','slug'=>'casual-slipon-brown','price'=>119.99,'old_price'=>150,'image'=>'/assets/img/demo/casual-slipon-brown.jpg','sizes'=>['45','44','43','42','41','40']],
                ['name'=>'حذاء كاجوال للرجال لون عسلي','slug'=>'casual-slipon-honey','price'=>119.99,'old_price'=>150,'image'=>'/assets/img/demo/casual-slipon-honey.jpg','sizes'=>['45','44','43','42','41','40']],
            ],

            // توبي سايدر
            'shoes/men/topsider' => [
                ['name'=>'توب سايدر شبابي — أبيض','slug'=>'topsider-youth-white','price'=>12.99,'old_price'=>50,'image'=>'/assets/img/demo/topsider-white.jpg','sizes'=>['42','41','40']],
                ['name'=>'حذاء فانت أونتشك 44 دي اكس — رمادي غامق','slug'=>'vans-authentic-44dx-grey','price'=>129.99,'old_price'=>390,'image'=>'/assets/img/demo/topsider-vans-grey.jpg','sizes'=>['41','40']],
                ['name'=>'حذاء جلد طبيعي رجالي — أسود','slug'=>'topsider-leather-black','price'=>77.00,'old_price'=>100,'image'=>'/assets/img/demo/topsider-leather-black.jpg','sizes'=>['40','39']],
                ['name'=>'حذاء جلد كاجوال رجالي — أسود','slug'=>'topsider-casual-black','price'=>49.99,'old_price'=>100,'image'=>'/assets/img/demo/topsider-casual-black.jpg','sizes'=>['44','43','42','41']],
                ['name'=>'توب سايدر شبابي — رمادي','slug'=>'topsider-youth-grey','price'=>12.99,'old_price'=>50,'image'=>'/assets/img/demo/topsider-grey.jpg','sizes'=>['42','41']],
                ['name'=>'توب سايدر شبابي — أسود','slug'=>'topsider-youth-black','price'=>12.99,'old_price'=>50,'image'=>'/assets/img/demo/topsider-black.jpg','sizes'=>['42','41']],
                ['name'=>'توب سايدر شبابي — أحمر غامق','slug'=>'topsider-youth-red1','price'=>12.99,'old_price'=>50,'image'=>'/assets/img/demo/topsider-red1.jpg','sizes'=>['42','41']],
                ['name'=>'توب سايدر شبابي — أحمر','slug'=>'topsider-youth-red2','price'=>12.99,'old_price'=>50,'image'=>'/assets/img/demo/topsider-red2.jpg','sizes'=>['42','41']],
            ],

            // طبية
            'shoes/men/medical' => [
                ['name'=>'زوج جوارب سيليكون طبية من Relax Foot','slug'=>'relax-foot-silicon-socks','price'=>9.99,'old_price'=>30,'image'=>'/assets/img/demo/medical-relax-foot.jpg'],
                ['name'=>'جوارب كعب سيليكون لمعالجة التشققات','slug'=>'silicon-heel-socks','price'=>9.99,'old_price'=>30,'image'=>'/assets/img/demo/medical-heel-cracks.jpg'],
                ['name'=>'زوج ضبان كعب سيليكون طبي — أزرق/برتقالي','slug'=>'silicon-heel-insoles-blue','price'=>9.99,'old_price'=>30,'image'=>'/assets/img/demo/medical-heel-blue.jpg'],
                ['name'=>'زوج ضبان كعب سيليكون طبي — شفاف/أزرق','slug'=>'silicon-heel-insoles-clear','price'=>9.99,'old_price'=>30,'image'=>'/assets/img/demo/medical-heel-clear.jpg'],
                ['name'=>'كعب جلد وفوم طبي لأوجاع الكعب والوقوف الطويل','slug'=>'leather-foam-heel','price'=>21.99,'old_price'=>30,'image'=>'/assets/img/demo/medical-leather-foam.jpg','sizes'=>['35-37']],
                ['name'=>'زوج ضبان كعب سيليكون طبي — أخضر/أصفر','slug'=>'silicon-heel-insoles-green','price'=>9.99,'old_price'=>30,'image'=>'/assets/img/demo/medical-heel-green.jpg'],
                ['name'=>'جبيرة سيليكون لتصحيح إبهام القدم','slug'=>'silicon-bunion-corrector','price'=>9.99,'old_price'=>30,'image'=>'/assets/img/demo/medical-bunion-silicon.jpg'],
                ['name'=>'مشد لتصحيح انحراف إبهام القدم','slug'=>'bunion-corrector-brace','price'=>11.99,'old_price'=>30,'image'=>'/assets/img/demo/medical-bunion-brace.jpg'],
            ],

            // صنادل
            'shoes/men/sandals' => [
                ['name'=>"adidas Men's ZNSORY Sandals - Beige",'slug'=>'adidas-znsory-beige','price'=>199.99,'old_price'=>230,'image'=>'/assets/img/demo/sandals-znsory-beige.jpg','sizes'=>['46','44','43','42','41','40']],
                ['name'=>"adidas Men's ZNSORY Sandals - Black",'slug'=>'adidas-znsory-black','price'=>199.99,'old_price'=>230,'image'=>'/assets/img/demo/sandals-znsory-black.jpg','sizes'=>['46','44','43','42','41','40']],
                ['name'=>"adidas Men's Adilette Comfort Slides - White",'slug'=>'adilette-comfort-white','price'=>139.99,'old_price'=>160,'image'=>'/assets/img/demo/sandals-adilette-white.jpg','sizes'=>['46','45','44','43','42','41','40']],
                ['name'=>"adidas Men's Adilette Comfort Slides - Black",'slug'=>'adilette-comfort-black','price'=>139.99,'old_price'=>160,'image'=>'/assets/img/demo/sandals-adilette-black.jpg','sizes'=>['46','45','44','43','42','41','40']],
                ['name'=>'adidas Men’s Adilette Clog 2.0 Sandals - Beige','slug'=>'adilette-clog-beige','price'=>179.99,'old_price'=>200,'image'=>'/assets/img/demo/sandals-adilette-clog-beige.jpg','sizes'=>['46','45','44','43','42','41','40']],
                ['name'=>'P-1-207777 حذاء جلد طبي موديل من جولف أند هورس للرجال','slug'=>'p1-207777-medical-clog','price'=>119.99,'old_price'=>180,'image'=>'/assets/img/demo/sandals-medical-clog-black.jpg','sizes'=>['46','45','44','43','42','41','40','38']],
                ['name'=>'PUMA DIVECAT V2 LITE Slides - Black','slug'=>'puma-divecat-black','price'=>89.99,'old_price'=>100,'image'=>'/assets/img/demo/sandals-puma-divecat-black.jpg','sizes'=>['46','45','44','43','42','40.5']],
                ['name'=>'PUMA DIVECAT V2 LITE Slides - Navy','slug'=>'puma-divecat-navy','price'=>89.99,'old_price'=>100,'image'=>'/assets/img/demo/sandals-puma-divecat-navy.jpg','sizes'=>['46','45','44','43','42','40.5']],

                ['name'=>'حفايه جلد طبيعي للرجال لون بني','slug'=>'leather-sandal-brown-a','price'=>49.99,'old_price'=>80,'image'=>'/assets/img/demo/sandals-leather-brown-a.jpg','sizes'=>['46','44','42','41','40']],
                ['name'=>'حفايه جلد طبيعي للرجال لون بني','slug'=>'leather-sandal-brown-b','price'=>49.99,'old_price'=>80,'image'=>'/assets/img/demo/sandals-leather-brown-b.jpg','sizes'=>['46','45','44','43','41','40']],
                ['name'=>'حفايه جلد طبيعي للرجال لون بني','slug'=>'leather-sandal-brown-c','price'=>54.99,'old_price'=>80,'image'=>'/assets/img/demo/sandals-leather-brown-c.jpg','sizes'=>['42','41','40','39','38','37','36','45','44','43']],
                ['name'=>'شِبشِب جلد طبيعي للرجال لون بني','slug'=>'leather-slide-brown-a','price'=>54.99,'old_price'=>80,'image'=>'/assets/img/demo/slides-leather-brown-a.jpg','sizes'=>['41','40','39','38','37','36']],
                ['name'=>"Skechers Mens' Tresmen - Ryer Sandal",'slug'=>'skechers-tresmen-ryer','price'=>189.99,'old_price'=>220,'image'=>'/assets/img/demo/sandals-skechers-ryer.jpg','sizes'=>['45','44','43','42','41']],
                ['name'=>"Skechers Women's Active Graceful - The Finish Slide",'slug'=>'skechers-active-graceful-finish','price'=>159.99,'old_price'=>200,'image'=>'/assets/img/demo/slides-skechers-graceful.jpg','sizes'=>['40','39','38.5','38','37.5','37','36.5']],
                ['name'=>'شِبشِب جلد طبيعي لون بني','slug'=>'leather-slide-brown-b','price'=>54.99,'old_price'=>80,'image'=>'/assets/img/demo/slides-leather-brown-b.jpg','sizes'=>['45','44','43','42','41','40']],
                ['name'=>'شِبشِب جلد طبيعي للرجال لون بني','slug'=>'leather-slide-brown-c','price'=>54.99,'old_price'=>80,'image'=>'/assets/img/demo/slides-leather-brown-c.jpg','sizes'=>['45','44','43','42','41','40']],
            ],




// ===== أحذية نسائية "رياضية"
'shoes/women/sport' => [
    [
        'name'      => "Skechers Women's Ultra Flex - Harmonious Shoes (Black)",
        'slug'      => 'w-skechers-ultra-flex-black',
        'price'     => 259.99,
        'old_price' => 300,
        'image'     => '/assets/img/demo/women-skechers-ultra-flex-black.jpg',
        'sizes'     => ['36','37','38','38.5','39','40'],
    ],
    [
        'name'      => "Skechers Women's Ultra Flex - Harmonious Shoes (White)",
        'slug'      => 'w-skechers-ultra-flex-white',
        'price'     => 259.99,
        'old_price' => 300,
        'image'     => '/assets/img/demo/women-skechers-ultra-flex-white.jpg',
        'sizes'     => ['36','37','38','38.5','39','40'],
    ],
    [
        'name'      => 'Reebok Royal Complete – White/Green',
        'slug'      => 'w-reebok-royal-complete-white-green',
        'price'     => 199.99,
        'old_price' => 400,
        'image'     => '/assets/img/demo/women-reebok-royal-complete-white-green.jpg',
        'sizes'     => ['35','35.5','36','36.5','37','37.5','38','39','40','40.5'],
    ],
    [
        'name'      => 'Skechers Smooth Street - Steady-Zip Shoes - White',
        'slug'      => 'w-skechers-smooth-street-white',
        'price'     => 149.99,
        'old_price' => 200,
        'image'     => '/assets/img/demo/women-skechers-smooth-street-white.jpg',
        'sizes'     => ['35','36','36.5','37','37.5','38','38.5','39','40'],
    ],
    [
        'name'      => 'حذاء يومي مريح نسائي - بيج',
        'slug'      => 'w-daily-slipon-beige',
        'price'     => 32.99,
        'old_price' => 80,
        'image'     => '/assets/img/demo/women-daily-slipon-beige.jpg',
        'sizes'     => ['36','37','38','39','40'],
    ],
    [
        'name'      => 'حذاء رياضي نسائي أبيض',
        'slug'      => 'w-casual-sport-white',
        'price'     => 32.99,
        'old_price' => 80,
        'image'     => '/assets/img/demo/women-sport-white.jpg',
        'sizes'     => ['36','37','38','39','40'],
    ],
    [
        'name'      => 'حذاء رياضي نسائي كحلي/رمادي',
        'slug'      => 'w-sport-navy-grey',
        'price'     => 15.99,
        'old_price' => 70,
        'image'     => '/assets/img/demo/women-sport-navy-grey.jpg',
        'sizes'     => ['36','37','38','39','40'],
    ],
    [
        'name'      => 'حذاء رياضي نسائي أسود/أبيض',
        'slug'      => 'w-sport-black-white',
        'price'     => 33.00,
        'old_price' => 80,
        'image'     => '/assets/img/demo/women-sport-black-white.jpg',
        'sizes'     => ['36','37','38','39','40'],
    ],
],


'shoes/women/boots' => [
    [
        'name'      => 'بسطار للنساء لون أسود',
        'slug'      => 'women-boots-black-zip',
        'price'     => 119.99,
        'old_price' => 200,
        'image'     => '/assets/img/demo/women-boots-black-zip.jpg',
        'sizes'     => ['41'],
        'brand'     => 'Sami Boutique',
        'color'     => 'Black',
    ],
    [
        'name'      => 'بسطار للنساء لون عسلي',
        'slug'      => 'women-boots-honey-zip',
        'price'     => 119.99,
        'old_price' => 200,
        'image'     => '/assets/img/demo/women-boots-honey-zip.jpg',
        'sizes'     => ['40','39'],
        'brand'     => 'Sami Boutique',
        'color'     => 'Beige',
    ],
    [
        'name'      => 'جزمة مع كعب للنساء لون أسود',
        'slug'      => 'women-ankle-heel-black',
        'price'     => 119.99,
        'old_price' => 200,
        'image'     => '/assets/img/demo/women-ankle-heel-black.jpg',
        'sizes'     => ['39','38','37','36'],
        'brand'     => 'Sami Boutique',
        'color'     => 'Black',
    ],
    [
        'name'      => 'حذاء شموه ساق طويل بسحاب جانبي للنساء لون عسلي',
        'slug'      => 'women-suede-long-camel',
        'price'     => 44.00,
        'old_price' => 80,
        'image'     => '/assets/img/demo/women-suede-long-camel.jpg',
        'sizes'     => ['38','37','36'],
        'brand'     => 'Rock',
        'color'     => 'Beige',
    ],
    [
        'name'      => 'جزمة كعب ونقشة جلد أفعى ساق طويل للنساء لون عسلي',
        'slug'      => 'women-long-snake-honey',
        'price'     => 77.00,
        'old_price' => 140,
        'image'     => '/assets/img/demo/women-long-snake-honey.jpg',
        'sizes'     => ['39','38','37','36'],
        'brand'     => 'Sami Boutique',
        'color'     => 'Beige',
    ],
    [
        'name'      => 'جزمة جلد بكعب عالي للنساء لون أسود',
        'slug'      => 'women-long-heel-black',
        'price'     => 77.00,
        'old_price' => 140,
        'image'     => '/assets/img/demo/women-long-heel-black.jpg',
        'sizes'     => ['43','39','36'],
        'brand'     => 'Sami Boutique',
        'color'     => 'Black',
    ],
    [
        'name'      => 'بسطار للنساء لون أسود',
        'slug'      => 'women-lace-boots-black',
        'price'     => 119.99,
        'old_price' => 200,
        'image'     => '/assets/img/demo/women-lace-boots-black.jpg',
        'sizes'     => ['38'],
        'brand'     => 'Sami Boutique',
        'color'     => 'Black',
    ],
    [
        'name'      => 'بسطار للنساء لون عسلي',
        'slug'      => 'women-lace-boots-honey',
        'price'     => 119.99,
        'old_price' => 200,
        'image'     => '/assets/img/demo/women-lace-boots-honey.jpg',
        'sizes'     => ['38'],
        'brand'     => 'Sami Boutique',
        'color'     => 'Beige',
    ],


    [
        'name'      => 'جزمة ساق متوسط الطول للنساء لون أبيض',
        'slug'      => 'women-mid-boots-white',
        'price'     => 24.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/women-mid-boots-white.jpg',
        'sizes'     => ['39','38'],
        'brand'     => 'Sami Boutique',
        'color'     => 'White',
    ],
    [
        'name'      => 'جزمة مخمل ساق طويل للنساء لون أسود',
        'slug'      => 'women-long-velvet-black',
        'price'     => 77.00,
        'old_price' => 140,
        'image'     => '/assets/img/demo/women-long-velvet-black.jpg',
        'sizes'     => ['39'],
        'brand'     => 'Sami Boutique',
        'color'     => 'Black',
    ],
    [
        'name'      => 'جزمة جلد بكعب ساق طويل للنساء لون أسود',
        'slug'      => 'women-long-heel-leather-black',
        'price'     => 77.00,
        'old_price' => 140,
        'image'     => '/assets/img/demo/women-long-heel-leather-black.jpg',
        'sizes'     => ['40','39','38','37','36'],
        'brand'     => 'Sami Boutique',
        'color'     => 'Black',
    ],
    [
        'name'      => 'جزمة جلد بكعب ساق طويل للنساء لون مموج أسود ورمادي',
        'slug'      => 'women-long-heel-leather-grey-pattern',
        'price'     => 77.00,
        'old_price' => 140,
        'image'     => '/assets/img/demo/women-long-heel-leather-grey-pattern.jpg',
        'sizes'     => ['41','40','39'],
        'brand'     => 'Sami Boutique',
        'color'     => 'Grey',
    ],
    [
        'name'      => 'جزمة مخمل ساق طويل للنساء لون أسود',
        'slug'      => 'women-long-velvet-black-2',
        'price'     => 77.00,
        'old_price' => 140,
        'image'     => '/assets/img/demo/women-long-velvet-black-2.jpg',
        'sizes'     => ['39','38','37','36'],
        'brand'     => 'Sami Boutique',
        'color'     => 'Black',
    ],
    [
        'name'      => 'جزمة جلد ساق طويل للنساء لون أسود',
        'slug'      => 'women-long-leather-black',
        'price'     => 77.00,
        'old_price' => 140,
        'image'     => '/assets/img/demo/women-long-leather-black.jpg',
        'sizes'     => ['39','38','37'],
        'brand'     => 'Sami Boutique',
        'color'     => 'Black',
    ],
    [
        'name'      => 'جزمة مخمل ساق طويل للنساء لون رمادي',
        'slug'      => 'women-long-velvet-grey',
        'price'     => 77.00,
        'old_price' => 140,
        'image'     => '/assets/img/demo/women-long-velvet-grey.jpg',
        'sizes'     => ['39','38'],
        'brand'     => 'Sami Boutique',
        'color'     => 'Grey',
    ],
    [
        'name'      => 'جزمة جلد ساق طويل للركبة للنساء لون رمادي',
        'slug'      => 'women-long-leather-knee-grey',
        'price'     => 77.00,
        'old_price' => 140,
        'image'     => '/assets/img/demo/women-long-leather-knee-grey.jpg',
        'sizes'     => ['37'],
        'brand'     => 'Sami Boutique',
        'color'     => 'Grey',
    ],

],

'shoes/women/heels' => [
    [
        'name'      => 'كندرة جلد كعب نسائي — لون بيج',
        'slug'      => 'women-heel-beige-strap',
        'price'     => 49.99,
        'old_price' => 65,
        'image'     => '/assets/img/demo/women-heel-beige-strap.jpg',
        'sizes'     => ['36','37','38','39','40','41'],
    ],
    [
        'name'      => 'كندرة جلد كعب نسائي — لون أبيض',
        'slug'      => 'women-heel-white-strap',
        'price'     => 49.99,
        'old_price' => 65,
        'image'     => '/assets/img/demo/women-heel-white-strap.jpg',
        'sizes'     => ['36','37','38','39','40','41'],
    ],
    [
        'name'      => 'كندرة جلد كعب مسمار للنساء — أسود',
        'slug'      => 'women-stiletto-heel-black',
        'price'     => 55.00,
        'old_price' => 70,
        'image'     => '/assets/img/demo/women-stiletto-heel-black.jpg',
        'sizes'     => ['36','37','38','39','40','41'],
    ],
    [
        'name'      => 'كندرة جلد طبية نسائية — أسود',
        'slug'      => 'women-medical-heel-black',
        'price'     => 49.99,
        'old_price' => 65,
        'image'     => '/assets/img/demo/women-medical-heel-black.jpg',
        'sizes'     => ['36','37','38','39','40','41'],
    ],
    [
        'name'      => 'بابوج ستاتي بكعب — لون بيج',
        'slug'      => 'women-mule-heel-beige',
        'price'     => 55.00,
        'old_price' => 70,
        'image'     => '/assets/img/demo/women-mule-heel-beige.jpg',
        'sizes'     => ['36','37','38','39','40','41'],
    ],
    [
        'name'      => 'بابوج ستاتي بكعب — لون أبيض',
        'slug'      => 'women-mule-heel-white',
        'price'     => 55.00,
        'old_price' => 70,
        'image'     => '/assets/img/demo/women-mule-heel-white.jpg',
        'sizes'     => ['36','37','38','39','40','41'],
    ],
    [
        'name'      => 'كندرة نسائية كعب مربّع — أسود',
        'slug'      => 'women-square-heel-black',
        'price'     => 55.00,
        'old_price' => 70,
        'image'     => '/assets/img/demo/women-square-heel-black.jpg',
        'sizes'     => ['36','37','38','39','40','41'],
    ],
    [
        'name'      => 'كندرة جلد كعب — أسود',
        'slug'      => 'women-heel-black-bow',
        'price'     => 49.99,
        'old_price' => 65,
        'image'     => '/assets/img/demo/women-heel-black-bow.jpg',
        'sizes'     => ['36','37','38','39','40','41'],
    ],
],

// ===== أحذية نسائية "طبي"
'shoes/women/medical' => [
    [
        'name'      => 'بابوج ستاتي طبي — لون بيج',
        'slug'      => 'women-medical-mule-peach',
        'price'     => 29.99,
        'old_price' => 50,
        'image'     => '/assets/img/demo/women-medical-mule-peach.jpg',
        'sizes'     => ['36','37','38','40','41'],
    ],
    [
        'name'      => 'بابوج طبي — لون كحلي',
        'slug'      => 'women-medical-mule-navy',
        'price'     => 55.00,
        'old_price' => 80,
        'image'     => '/assets/img/demo/women-medical-mule-navy.jpg',
        'sizes'     => ['36','37','38','39','40','41','42'],
    ],
    [
        'name'      => 'بابوج ستاتي طبي — لون سكري',
        'slug'      => 'women-medical-mule-cream',
        'price'     => 55.00,
        'old_price' => 80,
        'image'     => '/assets/img/demo/women-medical-mule-cream.jpg',
        'sizes'     => ['36','37','38','39','40','41'],
    ],
    [
        'name'      => 'صندل ستاتي طبي — أسود (سلسلة)',
        'slug'      => 'women-medical-sandal-black-studs',
        'price'     => 111.00,
        'old_price' => 180,
        'image'     => '/assets/img/demo/women-medical-sandal-black-studs.jpg',
        'sizes'     => ['36','37','38','39','40','41'],
    ],
    [
        'name'      => 'بابوج ستاتي طبي — لون عسلي (حزامين ڤلكرو)',
        'slug'      => 'women-medical-doublestrap-honey',
        'price'     => 69.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/women-medical-doublestrap-honey.jpg',
        'sizes'     => ['36','37','38','39','40','41'],
    ],
    [
        'name'      => 'بابوج ستاتي طبي — لون أبيض (حزامين ڤلكرو)',
        'slug'      => 'women-medical-doublestrap-white',
        'price'     => 69.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/women-medical-doublestrap-white.jpg',
        'sizes'     => ['36','37','38','39','40','41'],
    ],
    [
        'name'      => 'بابوج ستاتي طبي — بيج فاتح (حزامين ڤلكرو)',
        'slug'      => 'women-medical-doublestrap-beige',
        'price'     => 69.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/women-medical-doublestrap-beige.jpg',
        'sizes'     => ['36','37','38','39','40','41'],
    ],
    [
        'name'      => 'بابوج ستاتي طبي — أسود (فيونكة أمامية)',
        'slug'      => 'women-medical-mule-black-bow',
        'price'     => 29.99,
        'old_price' => 50,
        'image'     => '/assets/img/demo/women-medical-mule-black-bow.jpg',
        'sizes'     => ['36','37','38','39','40'],
    ],
],


// ===== أحذية نسائية "Bridal"
'shoes/women/bridal' => [
    [
        'name'      => 'حذاء باليه للنساء لون أخضر',
        'slug'      => 'women-bridal-ballet-green',
        'price'     => 16.00,
        'old_price' => 30,
        'image'     => '/assets/img/demo/bridal-ballet-green.jpg',
        'sizes'     => ['36','37','38','39','40','41'],
    ],
    [
        'name'      => 'حذاء زخاف للنساء لون أسود (شبكي)',
        'slug'      => 'women-bridal-loafer-black-1',
        'price'     => 16.00,
        'old_price' => 30,
        'image'     => '/assets/img/demo/bridal-loafer-black-1.jpg',
        'sizes'     => ['36','37','38','39','40','41'],
    ],
    [
        'name'      => 'حذاء زخاف للنساء لون أسود (مفرغ)',
        'slug'      => 'women-bridal-loafer-black-2',
        'price'     => 16.00,
        'old_price' => 30,
        'image'     => '/assets/img/demo/bridal-loafer-black-2.jpg',
        'sizes'     => ['36','37','38','39','40','41'],
    ],
    [
        'name'      => 'حذاء باليه للنساء لون خمري',
        'slug'      => 'women-bridal-ballet-maroon',
        'price'     => 16.00,
        'old_price' => 30,
        'image'     => '/assets/img/demo/bridal-ballet-maroon.jpg',
        'sizes'     => ['36','37','38','39','40','41'],
    ],
    [
        'name'      => 'حذاء باليه للنساء لون مشمشي',
        'slug'      => 'women-bridal-ballet-peach',
        'price'     => 16.00,
        'old_price' => 30,
        'image'     => '/assets/img/demo/bridal-ballet-peach.jpg',
        'sizes'     => ['36','37','38','39','40','41'],
    ],
    [
        'name'      => 'حذاء زخاف للنساء لون أحمر',
        'slug'      => 'women-bridal-loafer-red',
        'price'     => 16.00,
        'old_price' => 30,
        'image'     => '/assets/img/demo/bridal-loafer-red.jpg',
        'sizes'     => ['36','37','38','39','40','41'],
    ],
    [
        'name'      => 'حذاء باليه للنساء لون أسود (ترتر)',
        'slug'      => 'women-bridal-ballet-black',
        'price'     => 16.00,
        'old_price' => 30,
        'image'     => '/assets/img/demo/bridal-ballet-black.jpg',
        'sizes'     => ['36','37','38','39','40','41'],
    ],
    [
        'name'      => 'حذاء زخاف للنساء لون خمري',
        'slug'      => 'women-bridal-loafer-maroon',
        'price'     => 16.00,
        'old_price' => 30,
        'image'     => '/assets/img/demo/bridal-loafer-maroon.jpg',
        'sizes'     => ['36','37','38','39','40','41'],
    ],
],





// ===== أحذية نسائية "كاجوال"
'shoes/women/casual' => [
    [
        'name'      => 'كِندرة ستياني جلد للنساء — أسود',
        'slug'      => 'women-casual-black-plain',
        'price'     => 39.99,
        'old_price' => 60,
        'image'     => '/assets/img/demo/women-casual-black-plain.jpg',
        'sizes'     => ['36','37','38','39','40','41'],
    ],
    [
        'name'      => 'كِندرة ستياني حزام لصق للنساء — أسود',
        'slug'      => 'women-casual-strap-black',
        'price'     => 39.99,
        'old_price' => 60,
        'image'     => '/assets/img/demo/women-casual-strap-black.jpg',
        'sizes'     => ['36','37','38','39','40','41'],
    ],
    [
        'name'      => 'كِندرة ستياني حزام لصق للنساء — بيج',
        'slug'      => 'women-casual-strap-beige',
        'price'     => 39.99,
        'old_price' => 60,
        'image'     => '/assets/img/demo/women-casual-strap-beige.jpg',
        'sizes'     => ['36','37','38','39','40','41'],
    ],
    [
        'name'      => 'كِندرة ستياني جلد برباط للنساء — أسود',
        'slug'      => 'women-casual-lace-perf-black',
        'price'     => 39.99,
        'old_price' => 60,
        'image'     => '/assets/img/demo/women-casual-lace-perf-black.jpg',
        'sizes'     => ['36','37','38','39','40','41'],
    ],
    [
        'name'      => 'كِندرة ستياني جلد للنساء — عسلي',
        'slug'      => 'women-casual-buckle-sandal-brown',
        'price'     => 44.99,
        'old_price' => 60,
        'image'     => '/assets/img/demo/women-casual-buckle-sandal-brown.jpg',
        'sizes'     => ['36','37','38','39','40','41'],
    ],
    [
        'name'      => 'كِندرة ستياني جلد بحزام مطاطي — بيج',
        'slug'      => 'women-casual-elastic-beige',
        'price'     => 39.99,
        'old_price' => 60,
        'image'     => '/assets/img/demo/women-casual-elastic-beige.jpg',
        'sizes'     => ['36','37','38','39','40','41'],
    ],
    [
        'name'      => 'كِندرة ستياني جلد بحزام مطاطي — أسود',
        'slug'      => 'women-casual-elastic-black',
        'price'     => 39.99,
        'old_price' => 60,
        'image'     => '/assets/img/demo/women-casual-elastic-black.jpg',
        'sizes'     => ['36','37','38','39','40','41'],
    ],
    [
        'name'      => 'كِندرة ستياني جلد للنساء — أسود (سليب-أون)',
        'slug'      => 'women-casual-slipon-black',
        'price'     => 39.99,
        'old_price' => 60,
        'image'     => '/assets/img/demo/women-casual-slipon-black.jpg',
        'sizes'     => ['36','37','38','39','40','41'],
    ],
],







// ===== أحذية نسائية - صنادل
'shoes/women/sandals' => [
    [
        'name'      => 'حفايه طبية ولادي محير لون بني',
        'slug'      => 'women-sandal-kids-med-brown',
        'price'     => 39.99,
        'old_price' => 60,
        'image'     => '/assets/img/demo/women-sandal-kids-med-brown.jpg',
        'sizes'     => ['29','30','31','32','33','34','35','36','37','38','39','40'],
    ],
    [
        'name'      => "Skechers Women's Active Graceful Slide",
        'slug'      => 'skechers-women-active-graceful-slide',
        'price'     => 179.99,
        'old_price' => 220,
        'image'     => '/assets/img/demo/skechers-women-active-graceful-slide.jpg',
        'sizes'     => ['36','37','37.5','38','38.5','39','40'],
    ],
    [
        'name'      => 'صندل ستاتي طبي — أسود',
        'slug'      => 'women-medical-sandal-black-studs',
        'price'     => 111.00,
        'old_price' => 180,
        'image'     => '/assets/img/demo/women-medical-sandal-black-studs.jpg',
        'sizes'     => ['36','37','38','39','40','41'],
    ],
    [
        'name'      => 'بابوج ستاتي مفتوح لون بني فاتح',
        'slug'      => 'women-open-mule-tan',
        'price'     => 24.99,
        'old_price' => 35,
        'image'     => '/assets/img/demo/women-open-mule-tan.jpg',
        'sizes'     => ['36','37','38','39','40','41'],
    ],
    [
        'name'      => 'حفاية جلد طبيعي ولدي لون بني',
        'slug'      => 'women-leather-slide-brown',
        'price'     => 49.99,
        'old_price' => 80,
        'image'     => '/assets/img/demo/women-leather-slide-brown.jpg',
        'sizes'     => ['36','37','38','39','40','41'],
    ],
    [
        'name'      => 'صندل جلد طبيعي للنساء لون بني',
        'slug'      => 'women-natural-leather-sandal-brown-strap',
        'price'     => 49.99,
        'old_price' => 80,
        'image'     => '/assets/img/demo/women-natural-leather-sandal-brown-strap.jpg',
        'sizes'     => ['36','37','38','39','40','41'],
    ],
    [
        'name'      => 'صندل جلد طبيعي للنساء لون بني — موديل أربطة',
        'slug'      => 'women-natural-leather-sandal-brown-gladiator',
        'price'     => 69.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/women-natural-leather-sandal-brown-gladiator.jpg',
        'sizes'     => ['36','37','38','39','40','41'],
    ],
    [
        'name'      => 'حفاية جلد طبيعة ولدي لون بني',
        'slug'      => 'women-cork-slide-brown',
        'price'     => 39.99,
        'old_price' => 60,
        'image'     => '/assets/img/demo/women-cork-slide-brown.jpg',
        'sizes'     => ['33','34','35','36','37','38','39','40'],
    ],
],












// ===== أحذية أطفال (تظهر في /c/shoes/kids)
'shoes/kids' => [
    [
        'name'      => 'صندل أطفال ولادي مع لصق لون أسود وأخضر',
        'slug'      => 'kids-sandal-black-green-strap',
        'price'     => 44.00,
        'old_price' => 60,
        'image'     => '/assets/img/demo/kids-sandal-black-green-strap.jpg',
        'sizes'     => ['31','32','33','34','35','36'],
    ],
    [
        'name'      => 'حذاء رياضي محير ولادي لون كحلي ورمادي',
        'slug'      => 'kids-sport-mesh-navy-grey',
        'price'     => 15.99,
        'old_price' => 70,
        'image'     => '/assets/img/demo/kids-sport-mesh-navy-grey.jpg',
        'sizes'     => ['36','37','38','39','40'],
    ],
    [
        'name'      => 'بوت سبورت ولادي بشرطين (أبيض وكحلي)',
        'slug'      => 'kids-sneaker-2stripes-navy-white',
        'price'     => 33.00,
        'old_price' => 50,
        'image'     => '/assets/img/demo/kids-sneaker-2stripes-navy-white.jpg',
        'sizes'     => ['21','22','23','24','25','27'],
    ],
    [
        'name'      => 'بوت سبورت ولادي بشرِيط لصق لون أبيض',
        'slug'      => 'kids-sneaker-strap-white',
        'price'     => 34.99,
        'old_price' => 50,
        'image'     => '/assets/img/demo/kids-sneaker-strap-white.jpg',
        'sizes'     => ['26','27','28','29','30'],
    ],
    [
        'name'      => 'حذاء رياضي للأطفال والشباب — أبيض وأسود وأحمر ونعل أسود',
        'slug'      => 'kids-football-white-red-blacksole',
        'price'     => 19.99,
        'old_price' => 40,
        'image'     => '/assets/img/demo/kids-football-white-red-blacksole.jpg',
        'sizes'     => ['31','32','33','34','35','36','37','38','39','40','41','42'],
    ],
    [
        'name'      => 'حذاء رياضي للأطفال والشباب — أبيض وسكني وبرتقالي',
        'slug'      => 'kids-football-white-grey-orange',
        'price'     => 19.99,
        'old_price' => 40,
        'image'     => '/assets/img/demo/kids-football-white-grey-orange.jpg',
        'sizes'     => ['31','32','33','34','35','36','37','38','39','40','41','42'],
    ],
    [
        'name'      => 'حذاء رياضي للأطفال — أبيض وأسود وبرتقالي',
        'slug'      => 'kids-turf-white-black-orange',
        'price'     => 16.99,
        'old_price' => 40,
        'image'     => '/assets/img/demo/kids-turf-white-black-orange.jpg',
        'sizes'     => ['31','32','33','34','35','36','37','38','39','40','41','42'],
    ],
    [
        'name'      => 'Skechers Smooth Street - أحذية – Steady-Zip Shoes (أبيض)',
        'slug'      => 'skechers-smooth-street-steady-zip-white',
        'price'     => 149.99,
        'old_price' => 200,
        'image'     => '/assets/img/demo/skechers-smooth-street-steady-zip-white.jpg',
        'sizes'     => ['36','36.5','37','38','39','40'],
    ],
    [
        'name'      => "adidas Kids' Advantage Shoes - White",
        'slug'      => 'adidas-kids-advantage-white-1',
        'price'     => 129.99,
        'old_price' => 140,
        'image'     => '/assets/img/demo/adidas-kids-advantage-white-1.jpg',
        'sizes'     => ['20','21','22','23','23.5','24','25','26'],
    ],
    [
        'name'      => "adidas Womens' Samba OG Shoes - Beige",
        'slug'      => 'adidas-womens-samba-og-beige',
        'price'     => 339.99,
        'old_price' => 380,
        'image'     => '/assets/img/demo/adidas-womens-samba-og-beige.jpg',
        'sizes'     => ['36','37','37.3','38','38.7','39.3','40'],
    ],
    [
        'name'      => 'Reebok Wave Glider III Sandals (بناتي)',
        'slug'      => 'reebok-wave-glider-iii-pink',
        'price'     => 69.99,
        'old_price' => 130,
        'image'     => '/assets/img/demo/reebok-wave-glider-iii-pink.jpg',
        'sizes'     => ['21','23','24.5','25','26.5'],
    ],
    [
        'name'      => 'حذاء سكيتشرز جو رن 650 لون كحلي ونعل أبيض (أطفال)',
        'slug'      => 'skechers-gorun-650-kids-navy',
        'price'     => 88.00,
        'old_price' => 130,
        'image'     => '/assets/img/demo/skechers-gorun-650-kids-navy.jpg',
        'sizes'     => ['23'],
    ],
    [
        'name'      => "adidas Unisex RunFalcon 5 Shoes - Black",
        'slug'      => 'adidas-runfalcon-5-black',
        'price'     => 179.99,
        'old_price' => 200,
        'image'     => '/assets/img/demo/adidas-runfalcon-5-black.jpg',
        'sizes'     => ['36','36.7','37.3','38','38.7','39.3','40'],
    ],
    [
        'name'      => "adidas Unisex RunFalcon 5 Shoes - White",
        'slug'      => 'adidas-runfalcon-5-white',
        'price'     => 179.99,
        'old_price' => 200,
        'image'     => '/assets/img/demo/adidas-runfalcon-5-white.jpg',
        'sizes'     => ['36','36.7','37.3','38','38.7','39.3','40'],
    ],
    [
        'name'      => "adidas Unisex Ultrun 5 Shoes - Black",
        'slug'      => 'adidas-ultrun-5-black',
        'price'     => 239.99,
        'old_price' => 270,
        'image'     => '/assets/img/demo/adidas-ultrun-5-black.jpg',
        'sizes'     => ['36','36.7','37.3','38','38.7','39.3','40'],
    ],
    [
        'name'      => "adidas Kids' Advantage Shoes - White (نسخة أخرى)",
        'slug'      => 'adidas-kids-advantage-white-2',
        'price'     => 129.99,
        'old_price' => 140,
        'image'     => '/assets/img/demo/adidas-kids-advantage-white-2.jpg',
        'sizes'     => ['20','21','22','23','23.5','24','25','26'],
    ],
],






// ===== ملابس رجالي - Jeans (كل الموديلات الظاهرة في الصورة)
'clothing/men/jeans' => [
    // 1) بوي فِرند — أزرق غامق
    [
        'name'      => 'جينز موديل بوي فِرند سِرج عالي — بدون ليكار (أزرق غامق)',
        'slug'      => 'men-jeans-boyfriend-darkblue',
        'price'     => 99.99,
        'old_price' => 150,
        'image'     => '/assets/img/demo/men-jeans-boyfriend-darkblue.jpg',
        'sizes'     => ['30','31','32','33','34','36'],
    ],
    // 2) بوي فِرند — أزرق فاتح
    [
        'name'      => 'جينز موديل بوي فِرند سِرج عالي — بدون ليكار (أزرق فاتح)',
        'slug'      => 'men-jeans-boyfriend-lightblue',
        'price'     => 99.99,
        'old_price' => 150,
        'image'     => '/assets/img/demo/men-jeans-boyfriend-lightblue.jpg',
        'sizes'     => ['30','31'],
    ],
    // 3) بوي فِرند — كحلي
    [
        'name'      => 'جينز موديل بوي فِرند سِرج عالي — بدون ليكار (كحلي)',
        'slug'      => 'men-jeans-boyfriend-navy',
        'price'     => 99.99,
        'old_price' => 150,
        'image'     => '/assets/img/demo/men-jeans-boyfriend-navy.jpg',
        'sizes'     => ['30','31'],
    ],
    // 4) بوي فِرند — أزرق سماوي
    [
        'name'      => 'جينز موديل بوي فِرند سِرج عالي — بدون ليكار (أزرق سماوي)',
        'slug'      => 'men-jeans-boyfriend-skyblue',
        'price'     => 99.99,
        'old_price' => 150,
        'image'     => '/assets/img/demo/men-jeans-boyfriend-skyblue.jpg',
        'sizes'     => ['33','31'], // كما ظاهر على الكارد
    ],

    // 5) جينز خصر رباط (Gym/Jog) — أزرق فاتح
    [
        'name'      => 'جينز خصر رباط — بدون ليكار (أزرق فاتح)',
        'slug'      => 'men-jeans-jog-waist-lightblue',
        'price'     => 99.99,
        'old_price' => 150,
        'image'     => '/assets/img/demo/men-jeans-jog-waist-lightblue.jpg',
        'sizes'     => ['36','35','34','33','32','31','30'],
    ],
    // 6) جينز خصر رباط (Gym/Jog) — أزرق
    [
        'name'      => 'جينز خصر رباط — بدون ليكار (أزرق)',
        'slug'      => 'men-jeans-jog-waist-blue',
        'price'     => 99.99,
        'old_price' => 150,
        'image'     => '/assets/img/demo/men-jeans-jog-waist-blue.jpg',
        'sizes'     => ['31','30'],
    ],
    // 7) ممزق خفيف — أسود
    [
        'name'      => 'جينز ممزق خفيف — بدون ليكار (أسود)',
        'slug'      => 'men-jeans-ripped-black',
        'price'     => 99.99,
        'old_price' => 150,
        'image'     => '/assets/img/demo/men-jeans-ripped-black.jpg',
        'sizes'     => ['36','35','34','33','32','31','30'],
    ],
    // 8) ممزق خفيف — رمادي
    [
        'name'      => 'جينز ممزق خفيف — بدون ليكار (رمادي)',
        'slug'      => 'men-jeans-ripped-grey',
        'price'     => 99.99,
        'old_price' => 150,
        'image'     => '/assets/img/demo/men-jeans-ripped-grey.jpg',
        'sizes'     => ['36','35','34','33','32','31','30'],
    ],
],


// ===== ملابس رجالي - بنطلونات (كاجوال/كارغو)
'clothing/men/pants' => [
    [
        'name'      => 'بنطلون رجالي كاجوال جيب I CAN — لون زيتي',
        'slug'      => 'men-cargo-ican-olive',
        'price'     => 32.99,
        'old_price' => 70,
        'image'     => '/assets/img/demo/men-cargo-ican-olive.jpg',
        'sizes'     => ['S','M','L','XL'],
    ],
    [
        'name'      => 'بنطلون رجالي كاجوال جيب I CAN — لون رصاصي غامق',
        'slug'      => 'men-cargo-ican-dark-grey',
        'price'     => 32.99,
        'old_price' => 70,
        'image'     => '/assets/img/demo/men-cargo-ican-dark-grey.jpg',
        'sizes'     => ['S','M','L','XL'],
    ],
    [
        'name'      => 'بنطلون رجالي كاجوال جيب I CAN — لون أسود',
        'slug'      => 'men-cargo-ican-black',
        'price'     => 32.99,
        'old_price' => 70,
        'image'     => '/assets/img/demo/men-cargo-ican-black.jpg',
        'sizes'     => ['S','M','L','XL'],
    ],
    [
        'name'      => 'بنطلون شباب كتان لون بتولي Worker',
        'slug'      => 'men-worker-petrol',
        'price'     => 15.99,
        'old_price' => 160,
        'image'     => '/assets/img/demo/men-worker-petrol.jpg',
        'sizes'     => ['42','44','46','48'],
    ],
    [
        'name'      => 'بنطلون رجالي كاجوال جيب I CAN — لون أسود (موديل 2)',
        'slug'      => 'men-cargo-ican-black-v2',
        'price'     => 32.99,
        'old_price' => 70,
        'image'     => '/assets/img/demo/men-cargo-ican-black-v2.jpg',
        'sizes'     => ['S','M','L','XL'],
    ],
    [
        'name'      => 'بنطلون رجالي كاجوال جيب I CAN — لون كحلي',
        'slug'      => 'men-cargo-ican-navy',
        'price'     => 32.99,
        'old_price' => 70,
        'image'     => '/assets/img/demo/men-cargo-ican-navy.jpg',
        'sizes'     => ['S','M','L','XL'],
    ],
    [
        'name'      => 'بنطلون رجالي كاجوال جيب I CAN — لون رصاصي فاتح',
        'slug'      => 'men-cargo-ican-light-grey',
        'price'     => 32.99,
        'old_price' => 70,
        'image'     => '/assets/img/demo/men-cargo-ican-light-grey.jpg',
        'sizes'     => ['S','M','L','XL'],
    ],
    [
        'name'      => 'بنطلون رجالي كاجوال جيب I CAN — لون زيتي (موديل شعار)',
        'slug'      => 'men-cargo-ican-olive-logo',
        'price'     => 32.99,
        'old_price' => 70,
        'image'     => '/assets/img/demo/men-cargo-ican-olive-logo.jpg',
        'sizes'     => ['S','M','L','XL'],
    ],
],





// ===== ملابس رجالي - رياضة (بنطلونات رياضية)
'clothing/men/sport' => [
    [
        'name'      => 'ICAN TRAINING بنطلون شبابي صيفي سكني غامق',
        'slug'      => 'men-ican-training-dark-grey',
        'price'     => 24.99,
        'old_price' => 140,
        'image'     => '/assets/img/demo/men-ican-training-dark-grey.jpg',
        'sizes'     => ['S','M','L'],
    ],
    [
        'name'      => 'ICAN TRAINING بنطلون شبابي صيفي سكني فاتح',
        'slug'      => 'men-ican-training-light-grey',
        'price'     => 24.99,
        'old_price' => 140,
        'image'     => '/assets/img/demo/men-ican-training-light-grey.jpg',
        'sizes'     => ['S','M','L'],
    ],
    [
        'name'      => 'ICAN TRAINING بنطلون شبابي صيفي كحلي',
        'slug'      => 'men-ican-training-navy',
        'price'     => 24.99,
        'old_price' => 140,
        'image'     => '/assets/img/demo/men-ican-training-navy.jpg',
        'sizes'     => ['S','M','L'],
    ],
    [
        'name'      => 'ICAN TRAINING بنطلون شبابي صيفي أسود',
        'slug'      => 'men-ican-training-black',
        'price'     => 24.99,
        'old_price' => 140,
        'image'     => '/assets/img/demo/men-ican-training-black.jpg',
        'sizes'     => ['S','M','L'],
    ],
    [
        'name'      => 'ICAN WELL BEING TRAINING بنطلون شبابي صيفي',
        'slug'      => 'men-ican-wellbeing-training-black',
        'price'     => 24.99,
        'old_price' => 140,
        'image'     => '/assets/img/demo/men-ican-wellbeing-training-black.jpg',
        'sizes'     => ['S','M','L'],
    ],
    [
        'name'      => "Skechers Men's GOwalk Wear Expedition Jogger Pant (رمادي)",
        'slug'      => 'skechers-men-gowalk-wear-expedition-grey',
        'price'     => 89.99,
        'old_price' => 160,
        'image'     => '/assets/img/demo/skechers-men-gowalk-wear-expedition-grey.jpg',
        'sizes'     => ['S','M','L','XL','2XL'],
    ],
   
    [
        'name'      => 'Reebok Men Training French Terry Joggers (كحلي)',
        'slug'      => 'reebok-men-training-french-terry-navy',
        'price'     => 129.99,
        'old_price' => 200,
        'image'     => '/assets/img/demo/reebok-men-training-french-terry-navy.jpg',
        'sizes'     => ['S','M','L','XL','2XL'],
    ],
    [
        'name'      => "Skechers Men's Sporty skinny Pant (أسود)",
        'slug'      => 'skechers-men-sporty-skinny-black',
        'price'     => 99.99,
        'old_price' => 190,
        'image'     => '/assets/img/demo/skechers-men-sporty-skinny-black.jpg',
        'sizes'     => ['S','M','L','XL'],
    ],
],







'clothing/men/shorts' => [
    [
        'name'      => 'شورت جينز للرجال لون أصفر',
        'slug'      => 'men-shorts-jeans-yellow',
        'price'     => 24.99,
        'old_price' => 50,
        'image'     => '/assets/img/demo/men-shorts-jeans-yellow.jpg',
        'sizes'     => ['29'],
    ],
    [
        'name'      => 'شورت ستان للرجال لون أسود',
        'slug'      => 'men-shorts-satin-black',
        'price'     => 15.99,
        'old_price' => 30,
        'image'     => '/assets/img/demo/men-shorts-satin-black.jpg',
        'sizes'     => ['4XL','3XL','2XL','XL'],
    ],
    [
        'name'      => 'شورت ستان للرجال لون كحلي',
        'slug'      => 'men-shorts-satin-navy',
        'price'     => 15.99,
        'old_price' => 30,
        'image'     => '/assets/img/demo/men-shorts-satin-navy.jpg',
        'sizes'     => ['3XL','2XL','XL'],
    ],
    [
        'name'      => "Skechers Mens' Short",
        'slug'      => 'skechers-mens-short',
        'price'     => 99.99,
        'old_price' => 130,
        'image'     => '/assets/img/demo/skechers-mens-short.jpg',
        'sizes'     => ['M'],
    ],
    [
        'name'      => "Under Armour Men's Project Rock Ultimate 5\" Training",
        'slug'      => 'ua-project-rock-ultimate-5-training',
        'price'     => 99.99,
        'old_price' => 200,
        'image'     => '/assets/img/demo/ua-project-rock-ultimate-5-training.jpg',
        'sizes'     => ['S','M','L','XL'],
    ],
    [
        'name'      => 'شورت رجالي مع جيب وسحاب',
        'slug'      => 'men-shorts-zip-pocket',
        'price'     => 22.00,
        'old_price' => 30,
        'image'     => '/assets/img/demo/men-shorts-zip-pocket.jpg',
        'sizes'     => ['3XL','2XL','XL'],
    ],
    [
        'name'      => 'شورت جينز للرجال لون زهري فاتح',
        'slug'      => 'men-shorts-jeans-light-pink',
        'price'     => 15.99,
        'old_price' => 50,
        'image'     => '/assets/img/demo/men-shorts-jeans-light-pink.jpg',
        'sizes'     => ['42','40','38','36'],
    ],
    [
        'name'      => 'شورت جينز للرجال لون كحلي منقط',
        'slug'      => 'men-shorts-jeans-navy-dotted',
        'price'     => 15.99,
        'old_price' => 50,
        'image'     => '/assets/img/demo/men-shorts-jeans-navy-dotted1.jpg',
        'sizes'     => ['36'],
    ],
],









'clothing/men/shirts' => [
    [
        'name'      => 'قميص رجالي لون أحمر مع أزرار',
        'slug'      => 'men-shirt-red-buttons',
        'price'     => 15.99,
        'old_price' => 50,
        'image'     => '/assets/img/demo/men-shirt-red-buttons.jpg',
        'sizes'     => ['S'],
    ],
    [
        'name'      => 'قميص رجالي لون كحلي وبرني مع سحاب',
        'slug'      => 'men-shirt-navy-zip',
        'price'     => 15.99,
        'old_price' => 50,
        'image'     => '/assets/img/demo/men-shirt-navy-zip.jpg',
        'sizes'     => ['M','S'],
    ],
    [
        'name'      => 'قميص رسمي بأكمام طويلة للرجال لون أبيض',
        'slug'      => 'men-formal-shirt-white',
        'price'     => 319.99,
        'old_price' => 350,
        'image'     => '/assets/img/demo/men-formal-shirt-white.jpg',
        'sizes'     => ['L','M'],
    ],
    [
        'name'      => 'قميص رسمي بأكمام طويلة للرجال لون أبيض بخط أحمر',
        'slug'      => 'men-formal-shirt-white-redline',
        'price'     => 319.99,
        'old_price' => 350,
        'image'     => '/assets/img/demo/men-formal-shirt-white-redline.jpg',
        'sizes'     => ['L'],
    ],
    [
        'name'      => 'T-Shirt سلم فت قبة شبابي لون برتقالي',
        'slug'      => 'men-tshirt-orange-slimfit',
        'price'     => 59.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/men-tshirt-orange-slimfit.jpg',
        'sizes'     => ['M','L'],
    ],
    [
        'name'      => 'T-Shirt سلم فت قبة شبابي لون خمري',
        'slug'      => 'men-tshirt-wine-slimfit',
        'price'     => 59.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/men-tshirt-wine-slimfit.jpg',
        'sizes'     => ['2XL'],
    ],
    [
        'name'      => 'قميص كم للرجال لون أزرق غامق',
        'slug'      => 'men-shirt-blue-dark',
        'price'     => 33.00,
        'old_price' => 40,
        'image'     => '/assets/img/demo/men-shirt-blue-dark.jpg',
        'sizes'     => ['S'],
    ],
    [
        'name'      => 'قميص رجالي لون أخضر مستقي مع أزرار',
        'slug'      => 'men-shirt-green-buttons',
        'price'     => 15.99,
        'old_price' => 50,
        'image'     => '/assets/img/demo/men-shirt-green-buttons.jpg',
        'sizes'     => ['S'],
    ],
],



'clothing/men/tshirts' => [
    [
        'name'      => 'ICAN POLO T-SHIRT بولو تيشيرت شبابي لون زيتي',
        'slug'      => 'ican-polo-tshirt-olive',
        'price'     => 7.99,
        'old_price' => 40,
        'image'     => '/assets/img/demo/ican-polo-tshirt-olive.jpg',
        'sizes'     => ['L','M','S'],
    ],
    [
        'name'      => 'ICAN POLO T-SHIRT بولو تيشيرت شبابي لون أبيض',
        'slug'      => 'ican-polo-tshirt-white',
        'price'     => 7.99,
        'old_price' => 40,
        'image'     => '/assets/img/demo/ican-polo-tshirt-white.jpg',
        'sizes'     => ['L','M','S'],
    ],
    [
        'name'      => 'Reebok Graphic Series Vector T-Shirt أبيض',
        'slug'      => 'reebok-graphic-series-vector-white',
        'price'     => 69.99,
        'old_price' => 80,
        'image'     => '/assets/img/demo/reebok-graphic-series-vector-white.jpg',
        'sizes'     => ['XS','S','M','L'],
    ],
    [
        'name'      => 'تيشيرت شبابي قطن قبة دائرية لون زهري',
        'slug'      => 'round-neck-tee-pink',
        'price'     => 21.99,
        'old_price' => 50,
        'image'     => '/assets/img/demo/round-neck-tee-pink.jpg',
        'sizes'     => ['4XL','3XL','2XL','XL','L','M','S'],
    ],
    [
        'name'      => "Reebok Men's Classic Court Sport T-Shirt",
        'slug'      => 'reebok-classic-court-sport-tee',
        'price'     => 139.99,
        'old_price' => 150,
        'image'     => '/assets/img/demo/reebok-classic-court-sport-tee.jpg',
        'sizes'     => ['2XL','M','L'],
    ],
    [
        'name'      => "adidas Men's Tiro 25 Competition Training Jersey",
        'slug'      => 'adidas-tiro-25-training-jersey',
        'price'     => 139.99,
        'old_price' => 160,
        'image'     => '/assets/img/demo/adidas-tiro-25-training-jersey.jpg',
        'sizes'     => ['XL','L','S','XS'],
    ],
    [
        'name'      => 'Diadora MENS CTN SPANDEX SHIRT أبيض',
        'slug'      => 'diadora-cotton-spandex-shirt',
        'price'     => 33.00,
        'old_price' => 150,
        'image'     => '/assets/img/demo/diadora-cotton-spandex-shirt.jpg',
        'sizes'     => ['2XL','XL','L','M','S'],
    ],
    [
        'name'      => 'ICAN POLO T-SHIRT بولو تيشيرت شبابي لون رمادي سكني',
        'slug'      => 'ican-polo-tshirt-grey',
        'price'     => 7.99,
        'old_price' => 40,
        'image'     => '/assets/img/demo/ican-polo-tshirt-grey.jpg',
        'sizes'     => ['L','M','S'],
    ],
],

   'under-armour' => [
        [
            'name'      => "Under Armour Men's Project Rock Payoff Graphic Short",
            'slug'      => 'ua-project-rock-payoff-tee',
            'brand'     => 'Under Armour',
            'price'     => 140,
            'old_price' => 129.99,
            'image'     => '/assets/img/demo/ua-project-rock-payoff-tee.jpg',
            'sizes'     => ['S','M','L','XL','2XL'],
        ],
        [
            'name'      => "Under Armour Men's Armour Fleece Big Logo Hoodie",
            'slug'      => 'ua-armour-fleece-big-logo-hoodie',
            'brand'     => 'Under Armour',
            'price'     => 300,
            'old_price' => 239.99,
            'image'     => '/assets/img/demo/ua-armour-fleece-big-logo-hoodie.jpg',
            'sizes'     => ['S','M','L','XL','2XL'],
        ],
        [
            'name'      => "Under Armour Women's HeatGear Rib 1/4 Zip Long Sleeve",
            'slug'      => 'ua-w-heatgear-rib-quarter-zip',
            'brand'     => 'Under Armour',
            'price'     => 200,
            'old_price' => 159.99,
            'image'     => '/assets/img/demo/ua-w-heatgear-rib-quarter-zip.jpg',
            'sizes'     => ['XS','S','M','L'],
        ],
        [
            'name'      => "Under Armour Men's UA Heavyweight Logo Overlay Tee",
            'slug'      => 'ua-heavyweight-logo-overlay-tee',
            'brand'     => 'Under Armour',
            'price'     => 230,
            'old_price' => 149.99,
            'image'     => '/assets/img/demo/ua-heavyweight-logo-overlay-tee.jpg',
            'sizes'     => ['S','M','L','XL','2XL'],
        ],
        [
            'name'      => "Under Armour Men's Logo Embroidered Heavyweight Tee",
            'slug'      => 'ua-logo-embroidered-heavyweight-tee',
            'brand'     => 'Under Armour',
            'price'     => 190,
            'old_price' => 99.99,
            'image'     => '/assets/img/demo/ua-logo-embroidered-heavyweight-tee.jpg',
            'sizes'     => ['S','M','L'],
        ],
        [
            'name'      => "Under Armour Men's UA Phantom 4 Shoes",
            'slug'      => 'ua-phantom-4-shoes',
            'brand'     => 'Under Armour',
            'price'     => 700,
            'old_price' => 629.99,
            'image'     => '/assets/img/demo/ua-phantom-4-shoes.jpg',
            'sizes'     => ['41','42','42.5','43','44','44.5','45'],
        ],
        [
            'name'      => "Under Armour Men's UA Left Chest Logo Short Sleeve T-Shirt",
            'slug'      => 'ua-left-chest-logo-ss-tee',
            'brand'     => 'Under Armour',
            'price'     => 110,
            'old_price' => 99.99,
            'image'     => '/assets/img/demo/ua-left-chest-logo-ss-tee.jpg',
            'sizes'     => ['S','M','L','XL'],
        ],
        [
            'name'      => "Under Armour Men's UA Charged Edge Shoes",
            'slug'      => 'ua-charged-edge-shoes',
            'brand'     => 'Under Armour',
            'price'     => 400,
            'old_price' => 359.99,
            'image'     => '/assets/img/demo/ua-charged-edge-shoes.jpg',
            'sizes'     => ['41','42','42.5','43','44','44.5','45'],
        ],
    ],
'brands/hi-tec' => [
    [
        'name'      => 'Hi-Tec Altitude X-Plorer Waterproof – Brown',
        'slug'      => 'hi-tec-altitude-xplorer-brown',
        'price'     => 299.99,
        'old_price' => 360.00,
        'image'     => '/assets/img/demo/hi-tec-altitude-xplorer-brown.jpg',
        'sizes'     => ['40','41','42','43','43.5','44','44.5','46'],
    ],
    [
        'name'      => "Hi-Tec Men's Trail Pro WP – Black",
        'slug'      => 'hi-tec-trail-pro-wp-black',
        'price'     => 279.99,
        'old_price' => 300.00,
        'image'     => '/assets/img/demo/hi-tec-trail-pro-wp-black.jpg',
        'sizes'     => ['41','42','43','43.5','44','44.5'],
    ],
    [
        'name'      => "Hi-Tec Men's Trail Lite Mid WP – Black/Grey",
        'slug'      => 'hi-tec-trail-lite-mid-wp-black-grey',
        'price'     => 219.99,
        'old_price' => 280.00,
        'image'     => '/assets/img/demo/hi-tec-trail-lite-mid-wp-black-grey.jpg',
        'sizes'     => ['41','42','43','43.5','44','44.5','45','46'],
    ],
    [
        'name'      => 'Hi-Tec Altitude X-Plorer Waterproof – Black',
        'slug'      => 'hi-tec-altitude-xplorer-black',
        'price'     => 289.99,
        'old_price' => 360.00,
        'image'     => '/assets/img/demo/hi-tec-altitude-xplorer-black.jpg',
        'sizes'     => ['40','41','42','43','43.5','44','44.5'],
    ],
    [
        'name'      => 'Hi-Tec Picchu Mid – Grey/Orange',
        'slug'      => 'hi-tec-picchu-mid-grey-orange',
        'price'     => 239.99,
        'old_price' => 300.00,
        'image'     => '/assets/img/demo/hi-tec-picchu-mid-grey-orange.jpg',
        'sizes'     => ['40','41','42','43','43.5','44','44.5'],
    ],
    [
        'name'      => 'Hi-Tec Trail Lite WP – Olive',
        'slug'      => 'hi-tec-trail-lite-wp-olive',
        'price'     => 239.99,
        'old_price' => 260.00,
        'image'     => '/assets/img/demo/hi-tec-trail-lite-wp-olive.jpg',
        'sizes'     => ['40','41','42','43','43.5','44','44.5'],
    ],
    [
        'name'      => 'Hi-Tec Ravus Vent Lite Low WP – Navy',
        'slug'      => 'hi-tec-ravus-vent-lite-low-wp-navy',
        'price'     => 279.99,
        'old_price' => 350.00,
        'image'     => '/assets/img/demo/hi-tec-ravus-vent-lite-low-wp-navy.jpg',
        'sizes'     => ['40','41','42','43','43.5','44','45'],
    ],
    [
        'name'      => 'Hi-Tec Peak Backpack 18L – Navy/Orange',
        'slug'      => 'hi-tec-peak-backpack-18l-navy-orange',
        'price'     => 119.99,
        'old_price' => 140.00,
        'image'     => '/assets/img/demo/hi-tec-peak-18l-backpack-navy-orange.jpg',
        'sizes'     => [],
    ],
],
// ===== SOMI (Sami) – Kids & Women =====
// ملابس أطفال (SOMI)
'clothing/kids/somi' => [
    [
        'name'      => 'بيجامة شتوية قطعتين للأطفال لون زهري وكحلي من Sami',
        'slug'      => 'somi-kids-pajama-2pcs-pink-navy',
        'brand'     => 'SOMI',
        'price'     => 24.99,
        'old_price' => 40,
        'image'     => '/assets/img/demo/sami-kids-pajama-2pcs-pink-navy.jpg',
        'sizes'     => ['0-3 أشهر','3-6 أشهر','6-9 أشهر'],
    ],
    [
        'name'      => 'بيجامة شتوية 3 قطع للأطفال لون زهري وبيج من Sami',
        'slug'      => 'somi-kids-pajama-3pcs-pink-beige',
        'brand'     => 'SOMI',
        'price'     => 24.99,
        'old_price' => 40,
        'image'     => '/assets/img/demo/sami-kids-pajama-3pcs-pink-beige.jpg',
        'sizes'     => ['0-3 أشهر','3-6 أشهر','6-9 أشهر','9-12 أشهر'],
    ],
    [
        'name'      => 'بيجامة شتوية 3 قطع للأطفال لون أحمر وكحلي من Sami',
        'slug'      => 'somi-kids-pajama-3pcs-red-navy',
        'brand'     => 'SOMI',
        'price'     => 24.99,
        'old_price' => 40,
        'image'     => '/assets/img/demo/sami-kids-pajama-3pcs-red-navy.jpg',
        'sizes'     => ['0-3 أشهر','3-6 أشهر','6-9 أشهر','9-12 أشهر'],
    ],
    [
        'name'      => 'بيجامة شتوية قطعتين للأطفال لون بنفسجي بتصميم Be Groovy من Sami',
        'slug'      => 'somi-kids-pajama-2pcs-purple-groovy',
        'brand'     => 'SOMI',
        'price'     => 15.99,
        'old_price' => 25,
        'image'     => '/assets/img/demo/sami-kids-pajama-2pcs-purple-groovy.jpg',
        'sizes'     => ['6','8','10','12'],
    ],
    [
        'name'      => 'بيجامة شتوية قطعتين للأطفال لون أحمر وأسود (NYC) من Sami',
        'slug'      => 'somi-kids-pajama-2pcs-red-nyc',
        'brand'     => 'SOMI',
        'price'     => 15.99,
        'old_price' => 25,
        'image'     => '/assets/img/demo/sami-kids-pajama-2pcs-red-nyc.jpg',
        'sizes'     => ['6','8','10','12'],
    ],
    [
        'name'      => 'بيجامة شتوية قطعتين للأطفال لون مشمشي وأسود من Sami',
        'slug'      => 'somi-kids-pajama-2pcs-peach-black',
        'brand'     => 'SOMI',
        'price'     => 15.99,
        'old_price' => 25,
        'image'     => '/assets/img/demo/sami-kids-pajama-2pcs-peach-black.jpg',
        'sizes'     => ['6','8','10','12'],
    ],
    [
        'name'      => 'بيجامة شتوية قطعتين للأطفال لون زهري وأسود (NYC) من Sami',
        'slug'      => 'somi-kids-pajama-2pcs-pink-nyc',
        'brand'     => 'SOMI',
        'price'     => 15.99,
        'old_price' => 25,
        'image'     => '/assets/img/demo/sami-kids-pajama-2pcs-pink-nyc.jpg',
        'sizes'     => ['6','8','10','12'],
    ],
],



];

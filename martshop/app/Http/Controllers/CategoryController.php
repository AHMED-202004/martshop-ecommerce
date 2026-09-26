<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\CatalogQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /**
     * شجرة التصنيفات كاملة (عنوان + أطفال).
     */
    private array $TREE = [

        // أحذية
        'shoes' => [
            'title' => 'أحذية',
            'children' => [
                'men' => [
                    'title'    => 'أحذية رجالية',
                    'children' => [
                        'sport'    => 'رياضي',
                        'formal'   => 'رسمية وكلاسيك',
                        'boots'    => 'بسطاير/جزمة',
                        'casual'   => 'كاجوال',
                        'topsider' => 'توبي سايدر',
                        'medical'  => 'أحذية طبية',
                        'sandals'  => 'صنادل و شباشب',
                    ],
                ],
                'women' => [
                    'title'    => 'أحذية نسائية',
                    'children' => [
                        'sport'   => 'أحذية رياضية',
                        'boots'   => 'جزم وبسطاير',
                        'heels'   => 'روبي و كعب',
                        'medical' => 'أحذية طبية',
                        'bridal'  => 'بابيه و زفاف',
                        'casual'  => 'كاجوال',
                        'sandals' => 'صنادل و شباشب',
                    ],
                ],
                'kids' => [
                    'title'    => 'أحذية أطفال',
                 
                ],
            ],
        ],

        // الملابس
        'clothing' => [
            'title' => 'الملابس',
            'children' => [
                'men' => [
                    'title'    => 'ملابس رجالي',
                    'children' => [
                        'jeans'     => 'بناطيل جينز',
                        'pants'     => 'بناطيل كاجوال',
                        'sport'     => 'بناطيل رياضية',
                        'shorts'    => 'شورتات',
                        'shirts'    => 'قمصان',
                        'tshirts'   => 'تيشرتات',
                        'pajamas'   => 'بيجامات',
                        'jackets'   => 'جاكيتات ومعاطف',
                        'underwear' => 'ملابس داخلية',
                    ],
                ],
                'women' => [
                    'title'    => 'ملابس نسائي',
                    'children' => [
                        'underwear' => 'الملابس الداخلية',
                        'homewear'  => 'ملابس بيتية',
                        'pajamas'   => 'بيجامات',
                        'jackets'   => 'جاكيتات ومعاطف',
                        'tops'      => 'بلوز وقمصان',
                        'abayas'    => 'عبايات',
                        'dresses'   => 'فساتين',
                    ],
                ],
                'kids' => [
                    'title'    => 'ملابس أطفال',
                    'children' => [
                        'sets'      => 'بيجامات وأطقم',
                        'tops'      => 'بلوز وقمصان',
                        'dresses'   => 'فساتين',
                        'jackets'   => 'سترات وجاكيتات',
                        'bottoms'   => 'بناطيل و شورتات',
                        'underwear' => 'ملابس داخلية',
                        'blankets'  => 'كوفليات ومناشف',
                    ],
                ],
            ],
        ],

        // عطور
        'perfumes' => [
            'title' => 'عطور',
            'children' => [
                // يمين
                'men'     => 'عطور رجالية',
                'air'     => 'معطرات الجو والفراش',
                'bukhoor' => 'بخور',
                // يسار
                'women'   => 'عطور نسائية',
                'hair'    => 'عطور الشعر',
                'body'    => 'معطرات الجسم ومزيل العرق',
            ],
        ],

        // قسم الجمال
        'beauty' => [
            'title' => 'قسم الجمال',
            'children' => [
                'makeup' => [
                    'title' => 'الجمال',
                    'children' => [
                        'lips'  => 'مكياج الشفاه',
                        'eyes'  => 'مكياج العيون',
                        'face'  => 'مكياج الوجه',
                        'nails' => 'طلاء الأظافر',
                    ],
                ],
                'herb' => [
                    'title' => 'MART HERB',
                    'children' => [
                        'skin'     => 'العناية بالبشرة',
                        'body'     => 'العناية بالجسم',
                        'hair'     => 'العناية بالشعر',
                        'oral'     => 'العناية بالفم والأسنان',
                        'nailcare' => 'العناية بالأظافر',
                        'foot'     => 'العناية بالقدم',
                    ],
                ],
            ],
        ],

        // ساعات
        'watches' => [
            'title' => 'ساعات',
            'children' => [
                'men' => [
                    'title' => 'ساعات رجالية',
                    'children' => [
                        'leather' => 'ساعات جلدية',
                        'metal'   => 'ساعات معدنية',
                    ],
                ],
                'women' => [
                    'title' => 'ساعات نسائية',
                    'children' => [
                        'leather' => 'ساعات جلدية',
                        'metal'   => 'ساعات معدنية',
                    ],
                ],
                'smart' => 'ساعات ذكية',
            ],
        ],

        // إلكترونيات
        'electronics' => [
            'title' => 'إلكترونيات',
            'children' => [
                'phones' => [
                    'title' => 'موبايلات وأجهزة لوحية',
                    'children' => [
                        'samsung' => 'SAMSUNG',
                        'iphone'  => 'iPhone',
                        'xiaomi'  => 'Xiaomi',
                        'nokia'   => 'NOKIA',
                        'oppo'    => 'Oppo',
                        'realme'  => 'Realme',
                    ],
                ],
                'computing' => [
                    'title' => 'لابتوب وكمبيوتر',
                    'children' => [
                      
                        'pc-accessories' => 'اكسسوارات الكمبيوتر',
                    ],
                ],
                'mobile-acc' => [
                    'title' => 'سماعات',
                    'children' => [
                        'audio'  => 'سماعات',
                        
                    ],
                ],
            ],
        ],

        // شنط وإكسسوارات
        'bags' => [
            'title' => 'شنط واكسسوارات',
            'children' => [
                'women' => [
                    'title' => 'شنط وإكسسوارات',
                    'children' => [
                        'shoulder'         => 'شنط كتف',
                        'laptop'           => 'شنط لابتوب',
                        
                        'accessories-women'=> 'اكسسوارات للنساء',
                    ],
                ],
                'school' => [
                    'title' => 'مدرسة وقرطاسية',
                    'children' => [
                        'school'     => 'حقائب مدرسية',
                        'stationery' => 'قرطاسية',
                       
                    ],
                ],
                'men' => [
                    'title' => 'اكسسوارات رجال',
                    'children' => [
                        'wallets-men' => 'محافظ رجالية',
                        'belts'       => 'أحزمة',
                        'sunglasses'  => 'نظارات شمسية',
                    ],
                ],
            ],
        ],

        // الأطفال والألعاب
        'kids' => [
            'title' => 'الأطفال والألعاب',
            'children' => [
                'games' => [
                    'title' => 'الألعاب',
                    'children' => [
                    
                        'blocks'      => 'مستلزمات الأطفال والرضّع',
               
                    ],
                ],
                
            ],
        ],

        // الرياضة و الصحة
        'sporthealth' => [
            'title' => 'الرياضة و الصحة',
            'children' => [
                'sport' => [
                    'title' => 'الرياضة',
                    'children' => [
                        'shakers'     => 'شيكرات ومطارات',
                        'supplements' => 'مكملات غذائية وبروتين',
                        'home-gym'    => 'جيم في بيتك',
                        'football'    => 'منتجات كرة القدم',
                        'other'       => 'رياضات أخرى',
                    ],
                ],
                'health' => [
                    'title' => 'الصحة',
                    'children' => [
                        'braces'   => 'مشدات وأحزمة طبية',
                        'massage'  => 'أجهزة مساج وتدليك',
                        'personal' => 'العناية الشخصية',
                        'pillows'  => 'مخدات ومساند طبية',
                        'insoles'  => 'ضبانات',
                        'supplies' => 'مستلزمات طبية',
                    ],
                ],
            ],
        ],

       

        // كتب وروايات
        'books' => [
            'title' => 'كتب و روايات',
            'children' => [
                'education' => 'كتب تعليمية',
                'novels'    => 'روايات وقصص',
            ],
        ],
    ];

    /**
     * Transitional source used by the idempotent database importer.
     * Product demo data remains in this controller until its later migration.
     */
    public function legacyTree(): array
    {
        return $this->TREE;
    }

    /** صفحة الجذور */
    public function index()
    {
        if (Schema::hasTable('categories') && Category::query()->exists()) {
            $roots = Category::query()
                ->active()
                ->whereNull('parent_id')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['slug', 'name'])
                ->map(fn (Category $category) => [
                    'slug' => $category->slug,
                    'title' => $category->name,
                ])
                ->all();

            return view('category.index', compact('roots'));
        }

        if (! config('catalog.legacy_fallback_enabled', false)) {
            return view('category.index', ['roots' => []]);
        }

        $roots = [];
        foreach ($this->TREE as $slug => $node) {
            $roots[] = ['slug' => $slug, 'title' => $node['title'] ?? $this->pretty($slug)];
        }
        return view('category.index', compact('roots'));
    }

    public function gender(CatalogQuery $catalogue, string $gender)
    {
        abort_unless(in_array($gender, ['men', 'women'], true), 404);

        $databaseIsAuthoritative = Schema::hasTable('categories') && Category::query()->exists();

        $paths = ["shoes/{$gender}", "clothing/{$gender}", "perfumes/{$gender}", "watches/{$gender}"];
        if ($gender === 'women') {
            $paths[] = 'bags/women';
        }

        $products = collect($paths)
            ->flatMap(fn (string $path) => $catalogue->forCategoryPath($path))
            ->unique('slug')
            ->values();

        if ($products->isEmpty()
            && ! $databaseIsAuthoritative
            && config('catalog.legacy_fallback_enabled', false)) {
            $products = collect($this->aggregateProducts("shoes/{$gender}"))
                ->merge($this->aggregateProducts("clothing/{$gender}"))
                ->merge($this->aggregatePerfumes("perfumes/{$gender}"))
                ->merge($this->aggregateWatches("watches/{$gender}"));
            if ($gender === 'women') {
                $products = $products->merge($this->aggregateBags('bags/women'));
            }
            $products = $products->unique('slug')->values();
        }

        $title = $gender === 'men' ? 'قسم الرجال' : 'قسم النساء';
        $path = $gender;
        $children = [];
        $filters = $this->buildFilters($products);
        $breadcrumbs = [
            ['title' => 'الرئيسية', 'href' => url('/')],
            ['title' => 'التصنيفات', 'href' => route('categories.index')],
            ['title' => $title, 'href' => url("/c/{$gender}")],
        ];

        return view('category.show', compact(
            'title', 'path', 'children', 'filters', 'breadcrumbs', 'products'
        ));
    }

    /** عرض أي مستوى: /c/{path...} */
    public function show(?string $path = null)
    {
        abort_if($path !== null && strlen($path) > 500, 404);
        $path = trim($path, '/');
        $segments = array_values(array_filter(explode('/', $path), fn (string $segment) => $segment !== ''));
        foreach ($segments as $segment) {
            abort_unless(preg_match('/\A[A-Za-z0-9_-]{1,100}\z/', $segment) === 1, 404);
        }
        $segments = array_map('strtolower', $segments);

        if (empty($segments)) {
            return redirect()->route('categories.index');
        }

        // اختصارات beauty
        if ($segments[0] === 'beauty' && isset($segments[1])) {
            $second = $segments[1];
            $herbKeys   = ['skin','body','hair','oral','nailcare','foot'];
            $makeupKeys = ['lips','eyes','face','nails'];
            if (in_array($second, $herbKeys, true))   array_splice($segments, 1, 0, 'herb');
            elseif (in_array($second, $makeupKeys, true)) array_splice($segments, 1, 0, 'makeup');
        }

        // رابط نظامي
        $canonical = implode('/', $segments);
        if ($canonical !== $path) {
            return redirect('/c/' . $canonical, 301);
        }




// بعد تكوين $segments و $canonical مباشرةً
if (in_array($canonical, ['men','women'], true)) {
    $gender = $canonical;

    // سنجمع منتجات كل الأقسام الخاصة بهذا الجنس
    $pools = collect();

    // الأقسام الأساسية للجنسين: أحذية + ملابس + عطور + ساعات
    $nodes = [
        'shoes'     => 'aggregateShoes',     // أحذية
        'clothing'  => 'aggregateClothing',  // ملابس
        'perfumes'  => 'aggregatePerfumes',  // عطور
        'watches'   => 'aggregateWatches',   // ساعات
    ];

    // “شنط” للنساء فقط (حسب طلبك)
    if ($gender === 'women') {
        $nodes['bags'] = 'aggregateBags';
    }

    // لف على الأقسام واجلب بياناتها (aggregate* أو demoProducts)
    foreach ($nodes as $node => $aggregator) {
        // موجود فرع لهذا الجنس في شجرة التصنيفات؟
        if (isset($this->TREE[$node]['children'][$gender])) {
            $prefix = "{$node}/{$gender}";

            if (method_exists($this, $aggregator)) {
                // بعض الدوال عندك قد تستقبل (prefix) أو (path) كنص واحد؛
                // نمرّر النص مباشرةً مثل ما كنت عامل في مثالك.
                $pools = $pools->merge($this->{$aggregator}($prefix));
            } else {
                // داتا تجريبية إن ما في دالة aggregate
                $pools = $pools->merge($this->demoProducts($prefix));
            }
        }
    }

    // توحيد/إثراء العناصر مثل أسلوبك السابق
    $products = $pools->map(function ($p) {
        $p['brand'] = $p['brand'] ?? $this->guessBrand($p['name'] ?? '');
        $p['color'] = $p['color'] ?? $this->guessColor($p['name'] ?? '');
        $p['sizes'] = array_values(array_map(fn ($s) => (string) $s, $p['sizes'] ?? []));
        return $p;
    });

    // بناء الفلاتر من المنتجات
    $filters = $this->buildFilters($products);

    // مسار تنقّل + عنوان الصفحة
    $breadcrumbs = [
        ['title' => 'الرئيسية',    'href' => url('/')],
        ['title' => 'التصنيفات',  'href' => route('categories.index')],
        ['title' => $gender === 'men' ? 'قسم الرجال' : 'قسم النساء', 'href' => url("/c/{$gender}")],
    ];

    $children = []; // لا يوجد أبناء مباشرة لـ /c/men أو /c/women
    $title = $gender === 'men' ? 'قسم الرجال' : 'قسم النساء';
    $path  = $gender;

    // لاحظ اسم الفيو: استخدم نفس اللي عندك (category.show أو category.index)
    return view('category.show', compact('title', 'breadcrumbs', 'children', 'products', 'filters', 'path'));
}






        $breadcrumbs = [
            ['title' => 'الرئيسية',   'href' => url('/')],
            ['title' => 'التصنيفات', 'href' => route('categories.index')],
        ];
        $children = [];
        $currentTitle = null;
        $databaseCategory = $this->databaseCategory($canonical);

        $databaseIsAuthoritative = Schema::hasTable('categories') && Category::query()->exists();

        if (! $databaseCategory && $databaseIsAuthoritative) {
            abort(404);
        }

        if (! $databaseCategory && ! config('catalog.legacy_fallback_enabled', false)) {
            abort(404);
        }

        if ($databaseCategory) {
            foreach ($databaseCategory->ancestorsAndSelf() as $category) {
                $breadcrumbs[] = [
                    'title' => $category->name,
                    'href' => url('/c/'.$category->path),
                ];
            }

            $children = $databaseCategory->children
                ->where('status', 'active')
                ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
                ->pluck('name', 'slug')
                ->all();
            $currentTitle = $databaseCategory->name;

            $products = app(CatalogQuery::class)->forCategoryPath($canonical);
            $filters = $this->buildFilters($products);

            return view('category.show', [
                'path' => $canonical,
                'title' => $currentTitle,
                'breadcrumbs' => $breadcrumbs,
                'children' => $children,
                'segments' => $segments,
                'products' => $products,
                'filters' => $filters,
            ]);
        } else {
            // Safe fallback while the legacy product catalogue is being migrated.
            $node = $this->TREE;
            $hrefAcc = '/c';

            foreach ($segments as $seg) {
                if (isset($node[$seg])) {
                    $node = $node[$seg];
                } elseif (isset($node['children'][$seg])) {
                    $node = $node['children'][$seg];
                } else {
                    abort(404);
                }

                $title = is_array($node) && isset($node['title']) ? $node['title'] : $this->pretty($seg);
                $hrefAcc .= '/'.$seg;
                $breadcrumbs[] = ['title' => $title, 'href' => url($hrefAcc)];
                $currentTitle = $title;
            }

            if (isset($node['children']) && is_array($node['children'])) {
                foreach ($node['children'] as $slug => $child) {
                    $children[$slug] = is_array($child) ? ($child['title'] ?? $this->pretty($slug)) : $child;
                }
            }
        }

        // منتجات + فلاتر
       // منتجات + فلاتر (عام)
$products = collect();
$filters  = [];

// صفحات تحتاج تجميع من عدّة مسارات (مثلاً أحذية رجال/نساء أو الملابس العامة)
$aggregateable = [
    'shoes/men', 'shoes/women', 'shoes',
    'clothing/men', 'clothing/women', 'clothing/kids', 'clothing',
];

if (in_array($canonical, $aggregateable, true)) {
    // نجمع من أكثر من مسار
    $products = collect($this->aggregateProducts($canonical));
} else {
    // صفحة فرعية مباشرة (مثل perfumes/men) → خذها كما هي من الديمو
    $products = collect($this->demoProducts($canonical));
}

// نُثري الداتا لتشتغل الفلاتر (ماركة/لون/مقاسات) حتى لو جاي من demoProducts
$products = $products->map(function ($p) {
    $p['brand'] = $p['brand'] ?? $this->guessBrand($p['name'] ?? '');
    $p['color'] = $p['color'] ?? $this->guessColor($p['name'] ?? '');
    $p['sizes'] = array_values(array_map(fn ($s) => (string) $s, $p['sizes'] ?? []));
    return $p;
});

$filters = $this->buildFilters($products);
// داخل CategoryController@show … قرب هذا الجزء:
if ($canonical === 'shoes/men') {
    $products = collect($this->aggregateProducts('shoes/men'));
    $filters  = $this->buildFilters($products);

// أضف هذا البلوك للنسائي:
} elseif ($canonical === 'shoes/women') {
    $products = collect($this->aggregateProducts('shoes/women'));
    $filters  = $this->buildFilters($products);
} elseif ($canonical === 'shoes') { // <— الجديد
    $products = collect($this->aggregateProducts('shoes'));
    $filters  = $this->buildFilters($products);

} else {
    $products = collect($this->demoProducts($canonical));
    $filters  = [];
}
if ($canonical === 'clothing/men') {
    $products = collect($this->aggregateProducts('clothing/men'));
    $filters  = $this->buildFilters($products);
} elseif ($canonical === 'clothing/women') {
    $products = collect($this->aggregateProducts('clothing/women'));
    $filters  = $this->buildFilters($products);
} elseif ($canonical === 'clothing/kids') {
    $products = collect($this->aggregateProducts('clothing/kids'));
    $filters  = $this->buildFilters($products);
} elseif ($canonical === 'clothing') {
    $products = collect($this->aggregateProducts('clothing'));
    $filters  = $this->buildFilters($products);
}
// ====== تجميع الساعات ======
if ($canonical === 'watches') {
    $products = collect($this->aggregateWatches('watches'));
    $filters  = $this->buildFilters($products);

} elseif ($canonical === 'watches/men') {
    $products = collect($this->aggregateWatches('watches/men'));
    $filters  = $this->buildFilters($products);

} elseif ($canonical === 'watches/women') {
    $products = collect($this->aggregateWatches('watches/women'));
    $filters  = $this->buildFilters($products);

} elseif ($canonical === 'watches/smart') {
    $products = collect($this->aggregateWatches('watches/smart'));
    $filters  = $this->buildFilters($products);

// لو فرع نهائي (مثلاً watches/men/leather) خليه يمر كما هو من الديمو
} elseif (str_starts_with($canonical, 'watches/')) {
    $products = collect($this->demoProducts($canonical));
    $products = $products->map(function ($p) {
        $p['brand'] = $p['brand'] ?? $this->guessBrand($p['name'] ?? '');
        $p['color'] = $p['color'] ?? $this->guessColor($p['name'] ?? '');
        $p['sizes'] = array_values(array_map(fn ($s)=>(string)$s, $p['sizes'] ?? []));
        return $p;
    });
    $filters  = $this->buildFilters($products);
}
// ====== تجميع الإلكترونيات ======
if ($canonical === 'electronics') {
    $products = collect($this->aggregateElectronics('electronics'));
    $filters  = $this->buildFilters($products);

} elseif ($canonical === 'electronics/phones') {
    $products = collect($this->aggregateElectronics('electronics/phones'));
    $filters  = $this->buildFilters($products);

} elseif ($canonical === 'electronics/computing') {
    $products = collect($this->aggregateElectronics('electronics/computing'));
    $filters  = $this->buildFilters($products);

} elseif ($canonical === 'electronics/mobile-acc') {
    $products = collect($this->aggregateElectronics('electronics/mobile-acc'));
    $filters  = $this->buildFilters($products);

// لو ورقة نهائية مثل electronics/phones/samsung … إلخ → خذها كما هي من الديمو
} elseif (str_starts_with($canonical, 'electronics/')) {
    $products = collect($this->demoProducts($canonical));
    $products = $products->map(function ($p) {
        $p['brand'] = $p['brand'] ?? $this->guessBrand($p['name'] ?? '');
        $p['color'] = $p['color'] ?? $this->guessColor($p['name'] ?? '');
        $p['sizes'] = array_values(array_map(fn ($s)=>(string)$s, $p['sizes'] ?? []));
        return $p;
    });
    $filters  = $this->buildFilters($products);
}




// ===== شنط وإكسسوارات =====
if ($canonical === 'bags') {
    $products = collect($this->aggregateBags('bags'));
    $filters  = $this->buildFilters($products);

} elseif ($canonical === 'bags/women' || $canonical === 'bags/school' || $canonical === 'bags/men') {
    $products = collect($this->aggregateBags($canonical));
    $filters  = $this->buildFilters($products);

} elseif (str_starts_with($canonical, 'bags/')) {
    $products = collect($this->demoProducts($canonical));
    $products = $products->map(function ($p) {
        $p['brand'] = $p['brand'] ?? $this->guessBrand($p['name'] ?? '');
        $p['color'] = $p['color'] ?? $this->guessColor($p['name'] ?? '');
        $p['sizes'] = array_values(array_map(fn($s)=>(string)$s, $p['sizes'] ?? []));
        return $p;
    });
    $filters  = $this->buildFilters($products);
}






// ===== الأطفال والألعاب =====
if ($canonical === 'kids') {
    $products = collect($this->aggregateKids('kids'));
    $filters  = $this->buildFilters($products);

} elseif ($canonical === 'kids/games') {
    $products = collect($this->aggregateKids('kids/games'));
    $filters  = $this->buildFilters($products);

} elseif (str_starts_with($canonical, 'kids/')) {
    $products = collect($this->demoProducts($canonical));
    $products = $products->map(function ($p) {
        $p['brand'] = $p['brand'] ?? $this->guessBrand($p['name'] ?? '');
        $p['color'] = $p['color'] ?? $this->guessColor($p['name'] ?? '');
        $p['sizes'] = array_values(array_map(fn($s)=>(string)$s, $p['sizes'] ?? []));
        return $p;
    });
    $filters  = $this->buildFilters($products);
}

// ===== الرياضة و الصحة =====
if ($canonical === 'sporthealth') {
    $products = collect($this->aggregateSportHealth('sporthealth'));
    $filters  = $this->buildFilters($products);

} elseif ($canonical === 'sporthealth/sport' || $canonical === 'sporthealth/health') {
    $products = collect($this->aggregateSportHealth($canonical));
    $filters  = $this->buildFilters($products);

} elseif (str_starts_with($canonical, 'sporthealth/')) {
    $products = collect($this->demoProducts($canonical));
    $products = $products->map(function ($p) {
        $p['brand'] = $p['brand'] ?? $this->guessBrand($p['name'] ?? '');
        $p['color'] = $p['color'] ?? $this->guessColor($p['name'] ?? '');
        $p['sizes'] = array_values(array_map(fn($s)=>(string)$s, $p['sizes'] ?? []));
        return $p;
    });
    $filters  = $this->buildFilters($products);
}

// ===== كتب و روايات =====
if ($canonical === 'books') {
    $products = collect($this->aggregateBooks('books'));
    $filters  = $this->buildFilters($products);

} elseif ($canonical === 'books/education' || $canonical === 'books/novels') {
    $products = collect($this->aggregateBooks($canonical));
    $filters  = $this->buildFilters($products);

} elseif (str_starts_with($canonical, 'books/')) {
    $products = collect($this->demoProducts($canonical));
    $products = $products->map(function ($p) {
        $p['brand'] = $p['brand'] ?? $this->guessBrand($p['name'] ?? '');
        $p['color'] = $p['color'] ?? $this->guessColor($p['name'] ?? '');
        $p['sizes'] = array_values(array_map(fn($s)=>(string)$s, $p['sizes'] ?? []));
        return $p;
    });
    $filters  = $this->buildFilters($products);
}






if ($canonical === 'perfumes') {
    $products = collect($this->aggregatePerfumes('perfumes'));
    $filters  = $this->buildFilters($products);
} elseif (str_starts_with($canonical, 'perfumes/')) {
    $products = collect($this->aggregatePerfumes($canonical));
    $filters  = $this->buildFilters($products);
}
// لاحظ: $canonical = strtolower(trim($requestedPath, '/')); // حسب طريقتك
if ($canonical === 'beauty') {
    $products = collect($this->aggregateBeauty('beauty'));
    $filters  = $this->buildFilters($products);

} elseif (str_starts_with($canonical, 'beauty/makeup')) {
    // الجمال فقط
    $products = collect($this->aggregateBeauty($canonical === 'beauty/makeup'
        ? 'beauty/makeup'
        : $canonical)); // يسمح بالفرع النهائي مثل lips/eyes...
    $filters  = $this->buildFilters($products);

} elseif (str_starts_with($canonical, 'beauty/herb')) {
    // MART HERB فقط
    $products = collect($this->aggregateBeauty($canonical === 'beauty/herb'
        ? 'beauty/herb'
        : $canonical));
    $filters  = $this->buildFilters($products);

} elseif ($canonical === 'perfumes') {
    // كما عندك
    $products = collect($this->aggregatePerfumes('perfumes'));
    $filters  = $this->buildFilters($products);

} elseif (str_starts_with($canonical, 'perfumes/')) {
    // كما عندك
    $products = collect($this->aggregatePerfumes($canonical));
    $filters  = $this->buildFilters($products);
}


        $databaseProducts = app(CatalogQuery::class)->forCategoryPath($canonical);
        if ($databaseProducts->isNotEmpty()) {
            $products = $databaseProducts;
            $filters = $this->buildFilters($products);
        }

        return view('category.show', [
            'path'        => $canonical,
            'title'       => $currentTitle ?? 'التصنيف',
            'breadcrumbs' => $breadcrumbs,
            'children'    => $children,
            'segments'    => $segments,
            'products'    => $products,
            'filters'     => $filters,
        ]);
       
   
    }
    
    /** يجمّع منتجات الجمال (Makeup) و MART HERB بنفس أسلوب العطور */
private function aggregateBeauty(string $prefix): array
{
    $all = [];

    // أقسام الجمال (Makeup)
    $makeupLips       = ['beauty/makeup/lips'];
    $makeupEyes       = ['beauty/makeup/eyes'];
    $makeupFace       = ['beauty/makeup/face'];
    $makeupNails      = ['beauty/makeup/nails'];

    // أقسام MART HERB (العناية)
    $herbSkin        = ['beauty/herb/skin'];
    $herbBody        = ['beauty/herb/body'];
    $herbHair        = ['beauty/herb/hair'];
    $herbMouthTeeth  = ['beauty/herb/mouth_teeth'];
    $herbNailcare    = ['beauty/herb/nailcare'];
    $herbFoot        = ['beauty/herb/foot'];
    $herbOral        = ['beauty/herb/oral'];

    // مجموعات جاهزة حسب المستوى
    $makeupAll = array_merge($makeupLips, $makeupEyes, $makeupFace, $makeupNails);
    $herbAll   = array_merge($herbSkin, $herbBody, $herbHair, $herbMouthTeeth, $herbNailcare, $herbFoot, $herbOral);

    // من نجمع؟
    $paths = match ($prefix) {
        // صفحات make-up الرئيسية والفرعية
        'beauty/makeup'          => $makeupAll,
        'beauty/makeup/lips'     => $makeupLips,
        'beauty/makeup/eyes'     => $makeupEyes,
        'beauty/makeup/face'     => $makeupFace,
        'beauty/makeup/nails'     => $makeupNails,

        // صفحات herb الرئيسية والفرعية
        'beauty/herb'            => $herbAll,
        'beauty/herb/skin'       => $herbSkin,
        'beauty/herb/body'       => $herbBody,
        'beauty/herb/hair'       => $herbHair,
        'beauty/herb/mouth_teeth'=> $herbMouthTeeth,
        'beauty/herb/nailcare'   => $herbNailcare,
        'beauty/herb/foot'       => $herbFoot,
        'beauty/herb/oral'       => $herbOral,

        // صفحة "قسم الجمال" تجمع الاثنين
        'beauty'                 => array_merge($makeupAll, $herbAll),

        default                  => [], // مسار غير معروف
    };

    foreach ($paths as $p) {
        foreach ($this->demoProducts($p) as $prod) {
            // اكتمال بيانات الفلاتر (نفس نمطك تمامًا)
            $prod['brand'] = $prod['brand'] ?? $this->guessBrand($prod['name'] ?? '');
            $prod['color'] = $prod['color'] ?? $this->guessColor($prod['name'] ?? '');
            $prod['sizes'] = array_values(array_map(fn($s)=>(string)$s, $prod['sizes'] ?? []));
            $all[] = $prod;
        }
    }

    return $all;
}

// يجمع كل منتجات قسم العطور (أو فرع معيّن منه) من الديمو
private function aggregatePerfumes(string $prefix): array
{
    $all = [];

    // مسارات العطور المتوفرة في الديمو (مطابقة لما أضفته في demoProducts)
    $menPaths     = ['perfumes/men'];
    $womenPaths   = ['perfumes/women'];
    $airPaths     = ['perfumes/air'];
    $bukhoorPaths = ['perfumes/bukhoor'];
    $hairPaths    = ['perfumes/hair'];
    $bodyPaths    = ['perfumes/body'];

    // حدّد أي مجموعة نجمع بناءً على الـ prefix المطلوب
    $paths = match ($prefix) {
        'perfumes/men'     => $menPaths,
        'perfumes/women'   => $womenPaths,
        'perfumes/air'     => $airPaths,
        'perfumes/bukhoor' => $bukhoorPaths,
        'perfumes/hair'    => $hairPaths,
        'perfumes/body'    => $bodyPaths,

        // صفحة الجذر /c/perfumes → نجمع الكل
        'perfumes'         => array_merge(
            $menPaths, $womenPaths, $airPaths, $bukhoorPaths, $hairPaths, $bodyPaths
        ),

        // أو لو مرّرت المسار كامل مثل perfumes/men أو perfumes/air… الخ
        default => (str_starts_with($prefix, 'perfumes/')) ? [$prefix] : [],
    };

    foreach ($paths as $p) {
        foreach ($this->demoProducts($p) as $prod) {
            // اكتمال بيانات الفلاتر (ماركة/لون/قياسات)
            // العطور غالباً بلا قياسات؛ الماركة فقط تهمّ الفلاتر
            $prod['brand'] = $prod['brand'] ?? $this->guessBrand($prod['name'] ?? '');
            $prod['color'] = $prod['color'] ?? $this->guessColor($prod['name'] ?? ''); // تبقى آمنة لو في ألوان
            $prod['sizes'] = array_values(array_map(fn($s) => (string)$s, $prod['sizes'] ?? []));
            $all[] = $prod;
        }
    }

    return $all;
}
/** يجمع منتجات الإلكترونيات حسب المستوى (الكل أو فرع محدد) */
private function aggregateElectronics(string $prefix): array
{
    $all = [];

    // ثبّتي المسارات بناءً على الشجرة الحالية
    $phonesPaths = [
        'electronics/phones/samsung',
        'electronics/phones/iphone',
        'electronics/phones/xiaomi',
        'electronics/phones/nokia',
        'electronics/phones/oppo',
        'electronics/phones/realme',
    ];

    $computingPaths = [
        'electronics/computing/pc-accessories',
        // لو أضفتِ لاحقًا لابتوبات/كمبيوترات فرّعي هنا
        // 'electronics/computing/laptops', 'electronics/computing/desktops', ...
    ];

    $mobileAccPaths = [
        'electronics/mobile-acc/audio',
        // لو أضفتِ لاحقًا فروعًا ثانية للسماعات/الإكسسوارات ضيفيها هنا
        // 'electronics/mobile-acc/earbuds', ...
    ];

    // ماذا نجمع؟
    $paths = match ($prefix) {
        'electronics/phones'      => $phonesPaths,
        'electronics/computing'   => $computingPaths,
        'electronics/mobile-acc'  => $mobileAccPaths,
        'electronics'             => array_merge($phonesPaths, $computingPaths, $mobileAccPaths),
        default                   => (str_starts_with($prefix, 'electronics/')) ? [$prefix] : [],
    };

    foreach ($paths as $p) {
        foreach ($this->demoProducts($p) as $prod) {
            // نفس إثراء البيانات المستخدم بكل الأقسام
            $prod['brand'] = $prod['brand'] ?? $this->guessBrand($prod['name'] ?? '');
            $prod['color'] = $prod['color'] ?? $this->guessColor($prod['name'] ?? '');
            $prod['sizes'] = array_values(array_map(fn($s) => (string)$s, $prod['sizes'] ?? []));
            $all[] = $prod;
        }
    }

    return $all;
}
/** يجمع منتجات شنط وإكسسوارات */
private function aggregateBags(string $prefix): array
{
    $all = [];

    $womenPaths = [
        'bags/women/shoulder',
        'bags/women/laptop',
        'bags/women/accessories-women',
    ];
    $schoolPaths = [
        'bags/school/school',
        'bags/school/stationery',
    ];
    $menPaths = [
        'bags/men/wallets-men',
        'bags/men/belts',
        'bags/men/sunglasses',
    ];

    $paths = match ($prefix) {
        'bags/women'  => $womenPaths,
        'bags/school' => $schoolPaths,
        'bags/men'    => $menPaths,
        'bags'        => array_merge($womenPaths, $schoolPaths, $menPaths),
        default       => (str_starts_with($prefix, 'bags/')) ? [$prefix] : [],
    };

    foreach ($paths as $p) {
        foreach ($this->demoProducts($p) as $prod) {
            $prod['brand'] = $prod['brand'] ?? $this->guessBrand($prod['name'] ?? '');
            $prod['color'] = $prod['color'] ?? $this->guessColor($prod['name'] ?? '');
            $prod['sizes'] = array_values(array_map(fn($s)=>(string)$s, $prod['sizes'] ?? []));
            $all[] = $prod;
        }
    }
    return $all;
}

/** يجمع منتجات الأطفال والألعاب */
private function aggregateKids(string $prefix): array
{
    $all = [];

    // حسب الشجرة الحالية: kids/games/blocks (ويمكن تضيفي فروع لاحقًا)
    $gamesPaths = [
        'kids/games/blocks',
    ];

    $paths = match ($prefix) {
        'kids/games' => $gamesPaths,
        'kids'       => array_merge($gamesPaths),
        default      => (str_starts_with($prefix, 'kids/')) ? [$prefix] : [],
    };

    foreach ($paths as $p) {
        foreach ($this->demoProducts($p) as $prod) {
            $prod['brand'] = $prod['brand'] ?? $this->guessBrand($prod['name'] ?? '');
            $prod['color'] = $prod['color'] ?? $this->guessColor($prod['name'] ?? '');
            $prod['sizes'] = array_values(array_map(fn($s)=>(string)$s, $prod['sizes'] ?? []));
            $all[] = $prod;
        }
    }
    return $all;
}

/** يجمع منتجات الرياضة والصحة */
private function aggregateSportHealth(string $prefix): array
{
    $all = [];

    $sportPaths = [
        'sporthealth/sport/shakers',
        'sporthealth/sport/supplements',
        'sporthealth/sport/home-gym',
        'sporthealth/sport/football',
        'sporthealth/sport/other',
    ];
    $healthPaths = [
        'sporthealth/health/braces',
        'sporthealth/health/massage',
        'sporthealth/health/personal',
        'sporthealth/health/pillows',
        'sporthealth/health/insoles',
        'sporthealth/health/supplies',
    ];

    $paths = match ($prefix) {
        'sporthealth/sport'  => $sportPaths,
        'sporthealth/health' => $healthPaths,
        'sporthealth'        => array_merge($sportPaths, $healthPaths),
        default              => (str_starts_with($prefix, 'sporthealth/')) ? [$prefix] : [],
    };

    foreach ($paths as $p) {
        foreach ($this->demoProducts($p) as $prod) {
            $prod['brand'] = $prod['brand'] ?? $this->guessBrand($prod['name'] ?? '');
            $prod['color'] = $prod['color'] ?? $this->guessColor($prod['name'] ?? '');
            $prod['sizes'] = array_values(array_map(fn($s)=>(string)$s, $prod['sizes'] ?? []));
            $all[] = $prod;
        }
    }
    return $all;
}

/** يجمع منتجات الكتب والروايات */
private function aggregateBooks(string $prefix): array
{
    $all = [];

    $educationPaths = ['books/education'];
    $novelsPaths    = ['books/novels'];

    $paths = match ($prefix) {
        'books/education' => $educationPaths,
        'books/novels'    => $novelsPaths,
        'books'           => array_merge($educationPaths, $novelsPaths),
        default           => (str_starts_with($prefix, 'books/')) ? [$prefix] : [],
    };

    foreach ($paths as $p) {
        foreach ($this->demoProducts($p) as $prod) {
            $prod['brand'] = $prod['brand'] ?? $this->guessBrand($prod['name'] ?? '');
            $prod['color'] = $prod['color'] ?? $this->guessColor($prod['name'] ?? '');
            $prod['sizes'] = array_values(array_map(fn($s)=>(string)$s, $prod['sizes'] ?? []));
            $all[] = $prod;
        }
    }
    return $all;
}


private function aggregateWatches(string $prefix): array
{
    $all = [];

    // مسارات الساعات الموجودة في demoProducts()
    $menPaths    = ['watches/men/leather', 'watches/men/metal'];
    $womenPaths  = ['watches/women/leather', 'watches/women/metal'];
    $smartPaths  = ['watches/smart'];

    // نحدد ماذا نجمع حسب المسار المطلوب
    $paths = match ($prefix) {
        'watches/men'   => $menPaths,
        'watches/women' => $womenPaths,
        'watches/smart' => $smartPaths,
        'watches'       => array_merge($menPaths, $womenPaths, $smartPaths),
        default         => (str_starts_with($prefix, 'watches/')) ? [$prefix] : [],
    };

    foreach ($paths as $p) {
        foreach ($this->demoProducts($p) as $prod) {
            // إكمال بيانات الفلاتر مثل أسلوبك الحالي
            $prod['brand'] = $prod['brand'] ?? $this->guessBrand($prod['name'] ?? '');
            $prod['color'] = $prod['color'] ?? $this->guessColor($prod['name'] ?? '');
            $prod['sizes'] = array_values(array_map(fn($s)=>(string)$s, $prod['sizes'] ?? []));
            $all[] = $prod;
        }
    }

    return $all;
}
    private function databaseCategory(string $path): ?Category
    {
        if (! Schema::hasTable('categories')) {
            return null;
        }

        $category = Category::query()
            ->active()
            ->with(['parent', 'children'])
            ->where('path', $path)
            ->first();

        if ($category && collect($category->ancestorsAndSelf())
            ->contains(fn (Category $ancestor) => $ancestor->status !== 'active')) {
            return null;
        }

        return $category;
    }

    private function pretty(string $slug): string
    {
        return ucwords(str_replace('-', ' ', $slug));
    }

    /** يجمّع كل المنتجات تحت shoes/men (أو أي prefix) */
/**
 * يجمع المنتجات من demoProducts() لعدة مسارات مبنية على prefix
 * ينفع للأحذية والملابس بنفس الأسلوب.
 */
private function aggregateProducts(string $prefix): array
{
    $all = [];

    // أحذية
    $shoesMen = [
        'shoes/men/sport',
        'shoes/men/formal',
        'shoes/men/boots',
        'shoes/men/casual',
        'shoes/men/topsider',
        'shoes/men/medical',
        'shoes/men/sandals',
    ];

    $shoesWomen = [
        'shoes/women/sport',
        'shoes/women/boots',
        'shoes/women/heels',
        'shoes/women/medical',
        'shoes/women/bridal',
        'shoes/women/casual',
        'shoes/women/sandals',
    ];

    $shoesKids = [
        'shoes/kids', // أو 'shoes/kids/sport'.. حسب ما عندك في demoProducts()
    ];

    // ملابس (بنفس الفروع اللي عندك في الشجرة)
    $clothingMen = [
        'clothing/men/jeans',
        'clothing/men/pants',
        'clothing/men/sport',
        'clothing/men/shorts',
        'clothing/men/shirts',
        'clothing/men/tshirts',
        'clothing/men/pajamas',
        'clothing/men/jackets',
        'clothing/men/underwear',
    ];

    $clothingWomen = [
        'clothing/women/underwear',
        'clothing/women/homewear',
        'clothing/women/pajamas',
        'clothing/women/jackets',
        'clothing/women/tops',
        'clothing/women/abayas',
        'clothing/women/dresses',
    ];

    $clothingKids = [
        'clothing/kids/sets',
        'clothing/kids/tops',
        'clothing/kids/dresses',
        'clothing/kids/jackets',
        'clothing/kids/bottoms',
        'clothing/kids/underwear',
        'clothing/kids/blankets',
    ];
    

    // أي prefix → شو المسارات اللي بدنا نجمعها؟
    $paths = match ($prefix) {
        // أحذية
        'shoes/men'   => $shoesMen,
        'shoes/women' => $shoesWomen,
        'shoes/kids'  => $shoesKids,
        'shoes'       => array_merge($shoesMen, $shoesWomen, $shoesKids),

        // ملابس
        'clothing/men'   => $clothingMen,
        'clothing/women' => $clothingWomen,
        'clothing/kids'  => $clothingKids,
        'clothing'       => array_merge($clothingMen, $clothingWomen, $clothingKids),

        default => [],
    };

    foreach ($paths as $p) {
        foreach ($this->demoProducts($p) as $prod) {
            // نكمل بيانات الفلاتر (زي ما كنت تعمل)
            $prod['brand'] = $prod['brand'] ?? $this->guessBrand($prod['name'] ?? '');
            $prod['color'] = $prod['color'] ?? $this->guessColor($prod['name'] ?? '');
            $prod['sizes'] = array_values(array_map(fn($s) => (string)$s, $prod['sizes'] ?? []));
            $all[] = $prod;
        }
    }

    return $all;
}
// CategoryController.php

public function findDemoProductBySlug(string $slug): ?array
{
    foreach ($this->demoProducts('*') as $prod) {
        if (($prod['slug'] ?? null) === $slug) {
            return $prod;
        }
    }

    return null;
}

/**
 * Transitional source for the database catalogue importer.
 * Returns the legacy products grouped by their current category paths.
 */
public function legacyProductCatalogue(): array
{
    return $this->demoProducts('__catalogue_by_path__');
}


    /** يبني بيانات الفلاتر (ماركات/ألوان/قياسات) مع العدّادات */
    private function buildFilters(\Illuminate\Support\Collection $products): array
    {
        $brands = [];
        $colors = [];
        $sizes  = [];
        foreach ($products as $p) {
            if (!empty($p['brand'])) $brands[$p['brand']] = ($brands[$p['brand']] ?? 0) + 1;
            if (!empty($p['color'])) $colors[$p['color']] = ($colors[$p['color']] ?? 0) + 1;
            foreach (($p['sizes'] ?? []) as $s) {
                $sizes[(string)$s] = ($sizes[(string)$s] ?? 0) + 1;
            }
        }
        ksort($brands); ksort($colors); ksort($sizes, SORT_NATURAL);
        return ['brands' => $brands, 'colors' => $colors, 'sizes' => $sizes];
    }
    

    /** استنتاج ماركة من الاسم (بسيط مناسب للديمو) */
    private function guessBrand(string $name): ?string
    {
        $map = [
            'adidas'   => 'Adidas',
            'puma'     => 'Puma',
            'skechers' => 'Skechers',
            'hush'     => 'Hush Puppies',
            'diadora'  => 'Diadora',
            'hi-tec'   => 'HI-TEC',
            'reebok'   => 'Reebok',
            'vans'     => 'Vans',
            'nike'     => 'Nike',
            'leather'  => 'Leather',
            'جلد'      => 'Leather',
            'golf'     => 'GOLF & HORSE',
            'horse'    => 'GOLF & HORSE',
            'nautica' => 'Nautica',
        'rasasi'  => 'Rasasi', 'dareej' => 'Rasasi',
        'bogart'  => 'Jacques Bogart', 'silver scent' => 'Jacques Bogart',
        'armaf'   => 'Armaf', 'club de nuit' => 'Armaf',
        'ajmal'   => 'Ajmal',
        'arabiyat'=> 'Arabiyat',
        'lattafa' => 'Lattafa',
        'rue broca' => 'Rue Broca', 'hooked' => 'Rue Broca',
        'awaan'   => 'Awaan',
        'maharjan'=> 'Maharjan', 'mahrajan'=>'Maharjan',
        ];
        $low = mb_strtolower($name);
        foreach ($map as $k => $brand) {
            if (str_contains($low, $k)) return $brand;
        }
        return 'Generic';
    }

// (اختياري) مساعدتان صغيرتان
private function uniqueBy(array $items, string $key): array {
    $seen = [];
    $out  = [];
    foreach ($items as $it) {
        if (!isset($it[$key])) { $out[] = $it; continue; }
        if (isset($seen[$it[$key]])) continue;
        $seen[$it[$key]] = true;
        $out[] = $it;
    }
    return $out;
}

private function collectDemo(array $paths): array {
    $all = [];
    foreach ($paths as $p) {
        $all = array_merge($all, $this->demoProducts($p)); // موجودة عندك أصلاً
    }
    return $this->uniqueBy($all, 'slug'); // توحيد حسب الـ slug
}
    /** استنتاج اللون من الاسم */
    private function guessColor(string $name): ?string
    {
        $pairs = [
            'أبيض' => 'White','white'=>'White',
            'أسود' => 'Black','black'=>'Black',
            'رمادي'=>'Grey','gray'=>'Grey','grey'=>'Grey',
            'بني'  => 'Brown','brown'=>'Brown',
            'عسلي'=>'Beige','beige'=>'Beige',
            'أزرق' => 'Blue','blue'=>'Blue','navy'=>'Navy',
            'أحمر' => 'Red','red'=>'Red',
            'أخضر' => 'Green','green'=>'Green',
            'زيتي' => 'Olive','olive'=>'Olive',
        ];
        $low = mb_strtolower($name);
        foreach ($pairs as $k => $v) {
            if (str_contains($low, mb_strtolower($k))) return $v;
        }
        return null;
    }

    /** بيانات تجريبية */
public function demoProducts(string $path): array
    {
        $ph = '/assets/img/placeholder.png';
        $all = [

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
        'name'      => "Skechers Men's GOwalk Wear Expedition Jogger Pant (أسود)",
        'slug'      => 'skechers-men-gowalk-wear-expedition-black',
        'price'     => 89.99,
        'old_price' => 160,
        'image'     => '/assets/img/demo/skechers-men-gowalk-wear-expedition-black.jpg',
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












'clothing/men/pajamas' => [
    [
        'name'      => 'بيجامة ستان بأكمام طويلة (قميص وبنطال) للرجال لون كحلي مخطط',
        'slug'      => 'pajamas-satin-long-navy-striped',
        'price'     => 49.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/pajamas-satin-long-navy-striped.jpg',
        'sizes'     => ['54','52','50'],
    ],
    [
        'name'      => 'بيجامة ستان بأكمام طويلة (قميص وبنطال) للرجال لون خمري',
        'slug'      => 'pajamas-satin-long-burgundy',
        'price'     => 49.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/pajamas-satin-long-burgundy.jpg',
        'sizes'     => ['M','L'],
    ],
    [
        'name'      => 'بيجامة ستان بأكمام طويلة (قميص وبنطال) للرجال لون أسود مخطط',
        'slug'      => 'pajamas-satin-long-black-striped',
        'price'     => 49.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/pajamas-satin-long-black-striped.jpg',
        'sizes'     => ['52'],
    ],
    [
        'name'      => 'بيجامة ستان بأكمام طويلة (قميص وبنطال) للرجال لون خمري داكن',
        'slug'      => 'pajamas-satin-long-dark-burgundy',
        'price'     => 49.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/pajamas-satin-long-dark-burgundy.jpg',
        'sizes'     => ['54'],
    ],
    [
        'name'      => 'بيجامة ستان بأكمام طويلة (قميص وبنطال) للرجال لون رمادي مربعات (موديل 1)',
        'slug'      => 'pajamas-satin-long-grey-check-1',
        'price'     => 49.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/pajamas-satin-long-grey-check-1.jpg',
        'sizes'     => ['54'],
    ],
    [
        'name'      => 'بيجامة ستان بأكمام طويلة (قميص وبنطال) للرجال لون رمادي مربعات (موديل 2)',
        'slug'      => 'pajamas-satin-long-grey-check-2',
        'price'     => 49.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/pajamas-satin-long-grey-check-2.jpg',
        'sizes'     => ['52'],
    ],
    [
        'name'      => 'بيجامة ستان بأكمام طويلة (قميص وبنطال) للرجال لون كحلي سادة',
        'slug'      => 'pajamas-satin-long-navy',
        'price'     => 49.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/pajamas-satin-long-navy.jpg',
        'sizes'     => ['54'],
    ],
    [
        'name'      => 'بيجامة ستان بأكمام طويلة (قميص وبنطال) للرجال لون أزرق مخطط',
        'slug'      => 'pajamas-satin-long-blue-striped',
        'price'     => 49.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/pajamas-satin-long-blue-striped.jpg',
        'sizes'     => ['52','50'],
    ],
],





'clothing/men/jackets' => [
    [
        'name'      => 'سترة رجالية مبطنة ثقيلة لون بيج',
        'slug'      => 'men-jacket-heavy-beige',
        'price'     => 119.99,
        'old_price' => 140,
        'image'     => '/assets/img/demo/men-jacket-heavy-beige.jpg',
        'sizes'     => ['2XL','XL','M'],
    ],
    [
        'name'      => "adidas Mens' Own The Run 3-Stripes Jacket - White",
        'slug'      => 'adidas-own-the-run-3stripes-white',
        'price'     => 339.99,
        'old_price' => 380,
        'image'     => '/assets/img/demo/adidas-own-the-run-3stripes-white.jpg',
        'sizes'     => ['XL','L','M','S'],
    ],
    [
        'name'      => "adidas Mens' Adizero Essentials Running Jacket - Black",
        'slug'      => 'adidas-adizero-essentials-black',
        'price'     => 199.99,
        'old_price' => 320,
        'image'     => '/assets/img/demo/adidas-adizero-essentials-black.jpg',
        'sizes'     => ['L','M'],
    ],
    [
        'name'      => "adidas Essentials 3-Stripes Woven Windbreaker - Black",
        'slug'      => 'adidas-essentials-3stripes-windbreaker-black',
        'price'     => 319.99,
        'old_price' => 350,
        'image'     => '/assets/img/demo/adidas-essentials-3stripes-windbreaker-black.jpg',
        'sizes'     => ['2XL'],
    ],
    [
        'name'      => 'سترة رجالية مبطنة ثقيلة لون أسود',
        'slug'      => 'men-jacket-heavy-black',
        'price'     => 119.99,
        'old_price' => 140,
        'image'     => '/assets/img/demo/men-jacket-heavy-black.jpg',
        'sizes'     => ['L'],
    ],
    [
        'name'      => 'سترة رجالية مبطنة ثقيلة لون سكني',
        'slug'      => 'men-jacket-heavy-grey',
        'price'     => 88.00,
        'old_price' => 120,
        'image'     => '/assets/img/demo/men-jacket-heavy-grey.jpg',
        'sizes'     => ['2XL'],
    ],
    [
        'name'      => 'سترة رجالية مبطنة ثقيلة لون أسود (موديل 2)',
        'slug'      => 'men-jacket-heavy-black-2',
        'price'     => 88.00,
        'old_price' => 120,
        'image'     => '/assets/img/demo/men-jacket-heavy-black-2.jpg',
        'sizes'     => ['3XL'],
    ],
    [
        'name'      => 'سترة رجالية مبطنة ثقيلة لون زيتي',
        'slug'      => 'men-jacket-heavy-olive',
        'price'     => 119.99,
        'old_price' => 140,
        'image'     => '/assets/img/demo/men-jacket-heavy-olive.jpg',
        'sizes'     => ['2XL','XL','L'],
    ],
],









'clothing/men/underwear' => [
    [
        'name'      => 'Skechers Mens (41-46) 3 Pairs Socks - Black',
        'slug'      => 'skechers-3pairs-socks-black',
        'price'     => 29.99,
        'old_price' => 40,
        'image'     => '/assets/img/demo/skechers-3pairs-socks-black.jpg',
        'sizes'     => ['حجم واحد'],
    ],
    [
        'name'      => 'Skechers Mens (41-46) 3 Pairs Socks - White',
        'slug'      => 'skechers-3pairs-socks-white',
        'price'     => 29.99,
        'old_price' => 40,
        'image'     => '/assets/img/demo/skechers-3pairs-socks-white.jpg',
        'sizes'     => ['حجم واحد'],
    ],
    [
        'name'      => 'adidas Unisex Thin and Light Sportswear Ankle Socks 6',
        'slug'      => 'adidas-thin-light-ankle-socks-6',
        'price'     => 89.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/adidas-thin-light-ankle-socks-6.jpg',
        'sizes'     => ['L','M'],
    ],
    [
        'name'      => 'adidas Unisex Cushioned Sportswear Low-Cut Socks 6',
        'slug'      => 'adidas-cushioned-lowcut-socks-6',
        'price'     => 89.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/adidas-cushioned-lowcut-socks-6.jpg',
        'sizes'     => ['L','M'],
    ],
    [
        'name'      => 'شورت داخلي قطن 100% أبيض',
        'slug'      => 'men-cotton-boxer-white',
        'price'     => 12.99,
        'old_price' => 20,
        'image'     => '/assets/img/demo/men-cotton-boxer-white.jpg',
        'sizes'     => ['2XL','XL','L','M'],
    ],
    [
        'name'      => 'شباح قطن 100% أبيض',
        'slug'      => 'men-cotton-undershirt-tank-white',
        'price'     => 12.99,
        'old_price' => 20,
        'image'     => '/assets/img/demo/men-cotton-undershirt-tank-white.jpg',
        'sizes'     => ['2XL','XL','L'],
    ],
    [
        'name'      => 'فانيلة داخلية قطن 100% أبيض',
        'slug'      => 'men-cotton-inner-tee-white',
        'price'     => 15.99,
        'old_price' => 20,
        'image'     => '/assets/img/demo/men-cotton-inner-tee-white.jpg',
        'sizes'     => ['2XL','XL','L','M'],
    ],
    [
        'name'      => 'Skechers Mens (41-46) 3 Pairs Socks - White (Pack)',
        'slug'      => 'skechers-3pairs-socks-white-pack',
        'price'     => 29.99,
        'old_price' => 40,
        'image'     => '/assets/img/demo/skechers-3pairs-socks-white-pack.jpg',
        'sizes'     => ['حجم واحد'],
    ],
],








'clothing/women/underwear' => [
    [
        'name'      => 'adidas Unisex Thin and Light Sportswear Ankle Socks 6',
        'slug'      => 'adidas-thin-light-ankle-socks-6-w',
        'price'     => 89.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/adidas-thin-light-ankle-socks-6-w.jpg',
        'sizes'     => ['L','M'],
    ],
    [
        'name'      => 'adidas Unisex Cushioned Sportswear Low-Cut Socks 6',
        'slug'      => 'adidas-cushioned-lowcut-socks-6-w',
        'price'     => 89.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/adidas-cushioned-lowcut-socks-6-w.jpg',
        'sizes'     => ['L','M'],
    ],
    [
        'name'      => 'بدي سوت FULL LICRA نسائي لون رمادي',
        'slug'      => 'women-full-licra-bodysuit-grey',
        'price'     => 3.99,
        'old_price' => 25,
        'image'     => '/assets/img/demo/women-full-licra-bodysuit-grey.jpg',
        'sizes'     => ['2XL'],
    ],
    [
        'name'      => 'طقم داخلي (3 قطع) قطن من milk للنساء بألوان متعددة',
        'slug'      => 'milk-women-3pcs-cotton-set-multi',
        'price'     => 24.99,
        'old_price' => 30,
        'image'     => '/assets/img/demo/milk-women-3pcs-cotton-set-multi.jpg',
        'sizes'     => ['2XL','XL','L','S'],
    ],
    [
        'name'      => 'Kalia طقم داخلي هاي سبيد كووول (قطعتين) للنساء — متعدد الألوان',
        'slug'      => 'kalia-women-hi-speed-cool-2pcs',
        'price'     => 19.99,
        'old_price' => 30,
        'image'     => '/assets/img/demo/kalia-women-hi-speed-cool-2pcs.jpg',
        'sizes'     => ['4XL','3XL','2XL','XL'],
    ],
    [
        'name'      => 'Kalia طقم داخلي بناتي شورت مطبوع (قطعتين) — متعدد الألوان',
        'slug'      => 'kalia-girls-printed-short-2pcs',
        'price'     => 19.99,
        'old_price' => 30,
        'image'     => '/assets/img/demo/kalia-girls-printed-short-2pcs.jpg',
        'sizes'     => ['3XL','2XL','XL','L'],
    ],
    [
        'name'      => 'Kalia طقم داخلي حردة رقبة خصر عالي (قطعتين) للنساء — ألوان متعددة',
        'slug'      => 'kalia-women-high-waist-top-2pcs',
        'price'     => 19.99,
        'old_price' => 30,
        'image'     => '/assets/img/demo/kalia-women-high-waist-top-2pcs.jpg',
        'sizes'     => ['4XL','3XL','2XL','XL'],
    ],
    [
        'name'      => 'Kalia طقم داخلي سليب قطن (3 قطع) للنساء — ألوان متعددة',
        'slug'      => 'kalia-women-cotton-briefs-3pcs',
        'price'     => 19.99,
        'old_price' => 30,
        'image'     => '/assets/img/demo/kalia-women-cotton-briefs-3pcs.jpg',
        'sizes'     => ['3XL','2XL','XL','L'],
    ],
],









'clothing/women/homewear' => [
    [
        'name'      => 'بيجامة ستاتية أحجام كبيرة قماشة الزبدة (لون أحمر منقط)',
        'slug'      => 'plus-butter-pajama-red-dots',
        'price'     => 33.00,
        'old_price' => 50,
        'image'     => '/assets/img/demo/plus-butter-pajama-red-dots.jpg',
        'sizes'     => ['5XL','4XL','3XL','2XL'],
    ],
    [
        'name'      => 'جمبسوت قصير بناتي للنساء لون أزرق',
        'slug'      => 'women-short-romper-blue',
        'price'     => 49.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/women-short-romper-blue.jpg',
        'sizes'     => ['16','12'],
    ],
    [
        'name'      => 'جمبسوت قصير بناتي لون رمادي',
        'slug'      => 'women-short-romper-grey',
        'price'     => 49.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/women-short-romper-grey.jpg',
        'sizes'     => ['16','14','10','6'],
    ],
    [
        'name'      => 'فستان سهرة قصير — One Size',
        'slug'      => 'women-party-dress-short-one-size',
        'price'     => 69.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/women-party-dress-short-one-size.jpg',
        'sizes'     => ['حجم واحد'],
    ],
    [
        'name'      => 'بيجامة ستاتية أحجام كبيرة قماشة الزبدة (طقم رمادي)',
        'slug'      => 'plus-butter-pajama-grey',
        'price'     => 33.00,
        'old_price' => 50,
        'image'     => '/assets/img/demo/plus-butter-pajama-grey.jpg',
        'sizes'     => ['5XL','4XL','3XL','2XL'],
    ],
    [
        'name'      => 'بيجامة ستاتية أحجام كبيرة قماشة الزبدة (طقم أخضر)',
        'slug'      => 'plus-butter-pajama-green',
        'price'     => 33.00,
        'old_price' => 50,
        'image'     => '/assets/img/demo/plus-butter-pajama-green.jpg',
        'sizes'     => ['5XL','4XL','3XL','2XL'],
    ],
    [
        'name'      => 'بيجامة ستاتية أحجام كبيرة قماشة الزبدة (طقم وردي)',
        'slug'      => 'plus-butter-pajama-pink',
        'price'     => 33.00,
        'old_price' => 50,
        'image'     => '/assets/img/demo/plus-butter-pajama-pink.jpg',
        'sizes'     => ['5XL','4XL','3XL','2XL'],
    ],
    [
        'name'      => 'بيجامة ستاتية أحجام كبيرة قماشة الزبدة (طقم زيتوني)',
        'slug'      => 'plus-butter-pajama-olive',
        'price'     => 33.00,
        'old_price' => 50,
        'image'     => '/assets/img/demo/plus-butter-pajama-olive.jpg',
        'sizes'     => ['5XL','4XL','3XL','2XL'],
    ],
],












'clothing/women/pajamas' => [
    [
        'name'      => 'بيجامة قطن بأكمام قصيرة (3 قطع) للنساء لون أخضر',
        'slug'      => 'women-cotton-pajama-3pcs-green',
        'price'     => 49.99,
        'old_price' => 80,
        'image'     => '/assets/img/demo/women-cotton-pajama-3pcs-green.jpg',
        'sizes'     => ['2XL','XL','M'],
    ],
    [
        'name'      => 'بيجامة قطن بأكمام قصيرة للنساء لون زهري',
        'slug'      => 'women-cotton-pajama-pink',
        'price'     => 49.99,
        'old_price' => 80,
        'image'     => '/assets/img/demo/women-cotton-pajama-pink.jpg',
        'sizes'     => ['2XL','XL','M'],
    ],
    [
        'name'      => 'بيجامة قطن بأكمام قصيرة للنساء لون أصفر',
        'slug'      => 'women-cotton-pajama-yellow',
        'price'     => 49.99,
        'old_price' => 80,
        'image'     => '/assets/img/demo/women-cotton-pajama-yellow.jpg',
        'sizes'     => ['2XL','XL','M'],
    ],
    [
        'name'      => 'بيجامة ستاتية لون بنفسجي أشكال — One Size',
        'slug'      => 'women-satin-pajama-purple-onesize',
        'price'     => 19.99,
        'old_price' => 40,
        'image'     => '/assets/img/demo/women-satin-pajama-purple-onesize.jpg',
        'sizes'     => ['حجم واحد'],
    ],
    [
        'name'      => 'بيجامة قطن بأكمام قصيرة للنساء لون زهري (موديل 2)',
        'slug'      => 'women-cotton-pajama-pink-2',
        'price'     => 49.99,
        'old_price' => 80,
        'image'     => '/assets/img/demo/women-cotton-pajama-pink-2.jpg',
        'sizes'     => ['2XL','XL','L','M'],
    ],
    [
        'name'      => 'بيجامة قطن بأكمام قصيرة للنساء لون أخضر فاتح',
        'slug'      => 'women-cotton-pajama-light-green',
        'price'     => 49.99,
        'old_price' => 80,
        'image'     => '/assets/img/demo/women-cotton-pajama-light-green.jpg',
        'sizes'     => ['2XL','XL'],
    ],
    [
        'name'      => 'بيجامة قطن بأكمام قصيرة للنساء لون أزرق',
        'slug'      => 'women-cotton-pajama-blue',
        'price'     => 49.99,
        'old_price' => 80,
        'image'     => '/assets/img/demo/women-cotton-pajama-blue.jpg',
        'sizes'     => ['2XL','XL','M'],
    ],
    [
        'name'      => 'بيجامة قطن بأكمام قصيرة (3 قطع) للنساء لون بنفسجي',
        'slug'      => 'women-cotton-pajama-3pcs-purple',
        'price'     => 49.99,
        'old_price' => 80,
        'image'     => '/assets/img/demo/women-cotton-pajama-3pcs-purple.jpg',
        'sizes'     => ['2XL','XL'],
    ],
],








'clothing/women/jackets' => [
    [
        'name'      => 'جاكيت رسمي للنساء لون زهري',
        'slug'      => 'women-formal-jacket-pink',
        'price'     => 49.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/women-formal-jacket-pink.jpg',
        'sizes'     => ['42'],
    ],
    [
        'name'      => 'جاكيت صوف للنساء لون بني',
        'slug'      => 'women-wool-jacket-brown',
        'price'     => 49.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/women-wool-jacket-brown.jpg',
        'sizes'     => ['55'],
    ],
    [
        'name'      => 'جاكيت صوف للنساء لون رمادي',
        'slug'      => 'women-wool-jacket-grey',
        'price'     => 49.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/women-wool-jacket-grey.jpg',
        'sizes'     => ['55'],
    ],
    [
        'name'      => 'جاكيت صوف للنساء لون أخضر',
        'slug'      => 'women-wool-jacket-green',
        'price'     => 49.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/women-wool-jacket-green.jpg',
        'sizes'     => ['55'],
    ],
    [
        'name'      => 'جاكيت رسمي للنساء لون بني',
        'slug'      => 'women-formal-jacket-brown',
        'price'     => 49.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/women-formal-jacket-brown.jpg',
        'sizes'     => ['38'],
    ],
    [
        'name'      => 'جاكيت صوف قصير لون أسود',
        'slug'      => 'women-cropped-wool-jacket-black',
        'price'     => 49.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/women-cropped-wool-jacket-black.jpg',
        'sizes'     => ['16','14'],
    ],
    [
        'name'      => 'جاكيت شتوي للنساء لون زيتي (هودي طويل)',
        'slug'      => 'women-long-hooded-coat-olive',
        'price'     => 49.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/women-long-hooded-coat-olive.jpg',
        'sizes'     => ['46'],
    ],
    [
        'name'      => 'جاكيت مخمل للنساء لون برتقالي',
        'slug'      => 'women-velvet-jacket-orange',
        'price'     => 49.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/women-velvet-jacket-orange.jpg',
        'sizes'     => ['38'],
    ],
],








'clothing/women/tops' => [
    [
        'name'      => 'بلوزة صوف للنساء لون أسود (أكمام واسعة)',
        'slug'      => 'women-wool-top-black-wide-sleeve',
        'price'     => 49.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/women-wool-top-black-wide-sleeve.jpg',
        'sizes'     => ['55'],
    ],
    [
        'name'      => 'بلوزة صوف للنساء لون أسود (ياقة عالية)',
        'slug'      => 'women-wool-top-black-turtleneck',
        'price'     => 49.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/women-wool-top-black-turtleneck.jpg',
        'sizes'     => ['55'],
    ],
    [
        'name'      => 'بلوزة ربيعية للنساء لون رمادي',
        'slug'      => 'women-spring-top-grey',
        'price'     => 27.99,
        'old_price' => 40,
        'image'     => '/assets/img/demo/women-spring-top-grey.jpg',
        'sizes'     => ['M'],
    ],
    [
        'name'      => 'قميص صيفي للنساء لون بيج',
        'slug'      => 'women-summer-shirt-beige',
        'price'     => 65.99,
        'old_price' => 90,
        'image'     => '/assets/img/demo/women-summer-shirt-beige.jpg',
        'sizes'     => ['S'],
    ],
    [
        'name'      => 'بلوزة صوف خفيف للنساء لون أسود (كتف مفتوح)',
        'slug'      => 'women-light-wool-top-black-cold-shoulder',
        'price'     => 49.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/women-light-wool-top-black-cold-shoulder.jpg',
        'sizes'     => ['55'],
    ],
    [
        'name'      => 'بلوزة صوف للنساء لون أسود (ياقة V مع تطريز)',
        'slug'      => 'women-wool-top-black-vneck-emb',
        'price'     => 49.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/women-wool-top-black-vneck-emb.jpg',
        'sizes'     => ['55'],
    ],
    [
        'name'      => 'بلوزة صوف للنساء لون أسود (ياقة عالية كلاسيك)',
        'slug'      => 'women-wool-top-black-turtleneck-classic',
        'price'     => 49.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/women-wool-top-black-turtleneck-classic.jpg',
        'sizes'     => ['55'],
    ],
    [
        'name'      => 'بلوزة صوف للنساء لون أسود (أكمام واسعة – موديل 2)',
        'slug'      => 'women-wool-top-black-wide-sleeve-2',
        'price'     => 49.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/women-wool-top-black-wide-sleeve-2.jpg',
        'sizes'     => ['55'],
    ],
],










'clothing/women/abayas' => [
    [
        'name'      => 'عباية بتصميم تاجر لون أسود',
        'slug'      => 'abaya-trader-design-black',
        'price'     => 259.99,
        'old_price' => 320,
        'image'     => '/assets/img/demo/abaya-trader-design-black.jpg',
        'sizes'     => ['56'],
    ],
    [
        'name'      => 'عباية لون أبيض قطعتين مع شال',
        'slug'      => 'abaya-white-2pcs-with-shawl',
        'price'     => 299.99,
        'old_price' => 370,
        'image'     => '/assets/img/demo/abaya-white-2pcs-with-shawl.jpg',
        'sizes'     => ['54'],
    ],
    [
        'name'      => 'عباية لون أسود أكمام زم',
        'slug'      => 'abaya-black-gathered-sleeves',
        'price'     => 459.99,
        'old_price' => 550,
        'image'     => '/assets/img/demo/abaya-black-gathered-sleeves.jpg',
        'sizes'     => ['52'],
    ],
    [
        'name'      => 'فستان داخلي أبيض',
        'slug'      => 'inner-dress-white',
        'price'     => 89.99,
        'old_price' => 120,
        'image'     => '/assets/img/demo/inner-dress-white.jpg',
        'sizes'     => ['56'],
    ],
    [
        'name'      => 'عباية فلالي بيج غامق',
        'slug'      => 'abaya-falali-dark-beige',
        'price'     => 259.99,
        'old_price' => 320,
        'image'     => '/assets/img/demo/abaya-falali-dark-beige.jpg',
        'sizes'     => ['56'],
    ],
    [
        'name'      => 'عباية مخمل بيج',
        'slug'      => 'abaya-velvet-beige',
        'price'     => 259.99,
        'old_price' => 320,
        'image'     => '/assets/img/demo/abaya-velvet-beige.jpg',
        'sizes'     => ['58'],
    ],
    [
        'name'      => 'عباية ستان لون بيج',
        'slug'      => 'abaya-satin-beige',
        'price'     => 259.99,
        'old_price' => 320,
        'image'     => '/assets/img/demo/abaya-satin-beige.jpg',
        'sizes'     => ['56'],
    ],
    [
        'name'      => 'عباية دبل كلوش تفصيل لون أسود مع شال',
        'slug'      => 'abaya-double-cloche-black-with-shawl',
        'price'     => 459.99,
        'old_price' => 550,
        'image'     => '/assets/img/demo/abaya-double-cloche-black-with-shawl.jpg',
        'sizes'     => ['56'],
    ],
],








'clothing/women/dresses' => [
    [
        'name'      => 'فستان صيفي للنساء لون أصفر',
        'slug'      => 'summer-dress-yellow',
        'price'     => 65.99,
        'old_price' => 90,
        'image'     => '/assets/img/demo/summer-dress-yellow.jpg',
        'sizes'     => ['M'],
    ],
    [
        'name'      => 'فستان صيفي للنساء لون أبيض',
        'slug'      => 'summer-dress-white',
        'price'     => 129.99,
        'old_price' => 180,
        'image'     => '/assets/img/demo/summer-dress-white.jpg',
        'sizes'     => ['XS'],
    ],
    [
        'name'      => 'فستان صيفي للنساء لون كحلي مورد',
        'slug'      => 'summer-dress-navy-floral',
        'price'     => 65.99,
        'old_price' => 90,
        'image'     => '/assets/img/demo/summer-dress-navy-floral.jpg',
        'sizes'     => ['XL'],
    ],
    [
        'name'      => 'فستان للمناسبات للنساء لون زهري',
        'slug'      => 'evening-dress-pink',
        'price'     => 129.99,
        'old_price' => 180,
        'image'     => '/assets/img/demo/evening-dress-pink.jpg',
        'sizes'     => ['XL'],
    ],
    [
        'name'      => 'فستان قصير مخمل للنساء لون برتقالي',
        'slug'      => 'short-velvet-dress-orange',
        'price'     => 49.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/short-velvet-dress-orange.jpg',
        'sizes'     => ['42'],
    ],
    [
        'name'      => 'عباية صيفية ستان بارد للنساء',
        'slug'      => 'summer-satin-abaya',
        'price'     => 59.99,
        'old_price' => 80,
        'image'     => '/assets/img/demo/summer-satin-abaya.jpg',
        'sizes'     => ['حجم واحد'],
    ],
    [
        'name'      => 'أوفرول للنساء لون أحمر مورد',
        'slug'      => 'jumpsuit-red-floral',
        'price'     => 65.99,
        'old_price' => 90,
        'image'     => '/assets/img/demo/jumpsuit-red-floral.jpg',
        'sizes'     => ['M'],
    ],
    [
        'name'      => 'فستان صيفي للنساء لون أسود',
        'slug'      => 'summer-dress-black',
        'price'     => 65.99,
        'old_price' => 90,
        'image'     => '/assets/img/demo/summer-dress-black.jpg',
        'sizes'     => ['L'],
    ],
],













'clothing/kids/sets' => [
    [
        'name'      => 'بلوزة بيسك للأطفال لون زيتي',
        'slug'      => 'kids-basic-tee-olive',
        'price'     => 12.99,
        'old_price' => 25,
        'image'     => '/assets/img/demo/kids-basic-tee-olive.jpg',
        'sizes'     => ['8Y','5Y','6Y'],
    ],
    [
        'name'      => 'بلوزة قبة بولو للأطفال والشباب حتى 17 سنة (أزرق سماوي)',
        'slug'      => 'kids-youth-polo-skyblue',
        'price'     => 9.99,
        'old_price' => 30,
        'image'     => '/assets/img/demo/kids-youth-polo-skyblue.jpg',
        'sizes'     => ['7Y','8Y','9Y','12Y','13Y','16Y','17Y'],
    ],
    [
        'name'      => 'حرام بيبي لون رمادي',
        'slug'      => 'baby-blanket-grey',
        'price'     => 22.00,
        'old_price' => 30,
        'image'     => '/assets/img/demo/baby-blanket-grey.jpg',
        'sizes'     => ['One Size'],
    ],
    [
        'name'      => 'جوارب أطفال 5 أزواج (ألوان متعددة)',
        'slug'      => 'kids-socks-5pairs-multi',
        'price'     => 9.99,
        'old_price' => 15,
        'image'     => '/assets/img/demo/kids-socks-5pairs-multi.jpg',
        'sizes'     => ['2-3Y','6-8Y'],
    ],
    [
        'name'      => 'طقم داخلي شباح + شورت للأطفال لون كحلي بنجوم',
        'slug'      => 'kids-set-tank-short-navy-stars',
        'price'     => 9.99,
        'old_price' => 20,
        'image'     => '/assets/img/demo/kids-set-tank-short-navy-stars.jpg',
        'sizes'     => ['2-3Y'],
    ],
    [
        'name'      => 'طقم داخلي شباح + شورت للأطفال لون رمادي/وردي',
        'slug'      => 'kids-set-tank-short-grey-pink',
        'price'     => 9.99,
        'old_price' => 20,
        'image'     => '/assets/img/demo/kids-set-tank-short-grey-pink.jpg',
        'sizes'     => ['1-2Y'],
    ],
    [
        'name'      => 'طقم داخلي شباح + شورت للأطفال لون برتقالي بنجوم',
        'slug'      => 'kids-set-tank-short-orange-stars',
        'price'     => 9.99,
        'old_price' => 20,
        'image'     => '/assets/img/demo/kids-set-tank-short-orange-stars.jpg',
        'sizes'     => ['2-3Y','1-2Y'],
    ],
    [
        'name'      => 'طقم داخلي شباح + شورت للأطفال لون تركواز',
        'slug'      => 'kids-set-tank-short-turquoise',
        'price'     => 9.99,
        'old_price' => 20,
        'image'     => '/assets/img/demo/kids-set-tank-short-turquoise.jpg',
        'sizes'     => ['2-3Y','1-2Y'],
    ],
],







'clothing/kids/tops' => [
    [
        'name'      => 'بلوزة كت بناتي لون أبيض',
        'slug'      => 'girls-sleeveless-top-white',
        'price'     => 14.99,
        'old_price' => 25,
        'image'     => '/assets/img/demo/girls-sleeveless-top-white.jpg',
        'sizes'     => ['2Y','4Y','6Y','8Y'],
    ],
    [
        'name'      => 'بلوزة كت بناتي لون زهري',
        'slug'      => 'girls-sleeveless-top-pink',
        'price'     => 14.99,
        'old_price' => 25,
        'image'     => '/assets/img/demo/girls-sleeveless-top-pink.jpg',
        'sizes'     => ['2Y','4Y','6Y','8Y'],
    ],
    [
        'name'      => 'بلوزة كت بناتي لون أسود',
        'slug'      => 'girls-sleeveless-top-black',
        'price'     => 14.99,
        'old_price' => 25,
        'image'     => '/assets/img/demo/girls-sleeveless-top-black.jpg',
        'sizes'     => ['2Y','4Y','6Y','8Y'],
    ],
    [
        'name'      => 'بلوزة قبة بولو للأطفال والشباب حتى 17 سنة (أزرق سماوي)',
        'slug'      => 'kids-youth-polo-skyblue',
        'price'     => 9.99,
        'old_price' => 30,
        'image'     => '/assets/img/demo/kids-youth-polo-skyblue.jpg',
        'sizes'     => ['7Y','8Y','9Y','10Y','11Y','12Y','13Y','16Y'],
    ],
    [
        'name'      => 'بلوزة بناتي لون أسود (ياقة مزينة)',
        'slug'      => 'girls-top-black-emb-neck',
        'price'     => 26.99,
        'old_price' => 40,
        'image'     => '/assets/img/demo/girls-top-black-emb-neck.jpg',
        'sizes'     => ['2Y','3Y','4Y','5Y','6Y','8Y','10Y','12Y'],
    ],
    [
        'name'      => 'بلوزة بناتي لون زيتي',
        'slug'      => 'girls-top-olive',
        'price'     => 26.99,
        'old_price' => 40,
        'image'     => '/assets/img/demo/girls-top-olive.jpg',
        'sizes'     => ['2Y','3Y','4Y','5Y','6Y','8Y','10Y','12Y'],
    ],
    [
        'name'      => 'بلوزة بناتي لون أخضر',
        'slug'      => 'girls-top-green',
        'price'     => 26.99,
        'old_price' => 40,
        'image'     => '/assets/img/demo/girls-top-green.jpg',
        'sizes'     => ['2Y','3Y','4Y','5Y','6Y','8Y','10Y','12Y'],
    ],
    [
        'name'      => 'بلوزة بناتي لون فوشي',
        'slug'      => 'girls-top-fuchsia',
        'price'     => 26.99,
        'old_price' => 40,
        'image'     => '/assets/img/demo/girls-top-fuchsia.jpg',
        'sizes'     => ['2Y','3Y','4Y','5Y','6Y','8Y','10Y','12Y'],
    ],
],





'clothing/kids/dresses' => [
    [
        'name'      => 'فستان للأطفال لون زهري',
        'slug'      => 'kids-dress-pink',
        'price'     => 49.99,
        'old_price' => 70,
        'image'     => '/assets/img/demo/kids-dress-pink.jpg',
        'sizes'     => ['5Y','3Y'],
    ],
    [
        'name'      => 'فستان للأطفال لون بنفسجي',
        'slug'      => 'kids-dress-purple',
        'price'     => 49.99,
        'old_price' => 70,
        'image'     => '/assets/img/demo/kids-dress-purple.jpg',
        'sizes'     => ['5Y','4Y','2Y'],
    ],
    [
        'name'      => 'فستان للأطفال لون أخضر',
        'slug'      => 'kids-dress-green',
        'price'     => 49.99,
        'old_price' => 70,
        'image'     => '/assets/img/demo/kids-dress-green.jpg',
        'sizes'     => ['5Y','4Y','3Y','2Y'],
    ],
    [
        'name'      => 'فستان للأطفال لون أبيض',
        'slug'      => 'kids-dress-white',
        'price'     => 49.99,
        'old_price' => 70,
        'image'     => '/assets/img/demo/kids-dress-white.jpg',
        'sizes'     => ['5Y','4Y','3Y','2Y'],
    ],
    [
        'name'      => 'فستان حفلة لون بيج فاتح (بناتي)',
        'slug'      => 'girls-party-dress-beige',
        'price'     => 26.99,
        'old_price' => 40,
        'image'     => '/assets/img/demo/girls-party-dress-beige.jpg',
        'sizes'     => ['10Y','8Y','6Y'],
    ],
    [
        'name'      => 'فستان نص كم بناتي لون أصفر برسومات نحلة',
        'slug'      => 'girls-half-sleeve-dress-yellow-bee',
        'price'     => 26.99,
        'old_price' => 40,
        'image'     => '/assets/img/demo/girls-half-sleeve-dress-yellow-bee.jpg',
        'sizes'     => ['6Y'],
    ],
    [
        'name'      => 'فستان نص كم بناتي لون أبيض برسومات زيتوني',
        'slug'      => 'girls-half-sleeve-dress-white-olive-print',
        'price'     => 26.99,
        'old_price' => 40,
        'image'     => '/assets/img/demo/girls-half-sleeve-dress-white-olive-print.jpg',
        'sizes'     => ['10Y','8Y','6Y'],
    ],
    [
        'name'      => 'فستان نص كم بناتي لون أبيض برسومات قلب أحمر',
        'slug'      => 'girls-half-sleeve-dress-white-red-hearts',
        'price'     => 26.99,
        'old_price' => 40,
        'image'     => '/assets/img/demo/girls-half-sleeve-dress-white-red-hearts.jpg',
        'sizes'     => ['10Y'],
    ],
],








'clothing/kids/jackets' => [
    [
        'name'      => 'سترة بناتية مبطنة مع طاقية لون أسود',
        'slug'      => 'girls-padded-hooded-jacket-black',
        'price'     => 89.99,
        'old_price' => 120,
        'image'     => '/assets/img/demo/girls-padded-hooded-jacket-black.jpg',
        'sizes'     => ['6Y','8Y','10Y','12Y','14Y','16Y'],
    ],
    [
        'name'      => 'سترة بناتية مبطنة مع طاقية لون زهري فاتح',
        'slug'      => 'girls-padded-hooded-jacket-light-pink',
        'price'     => 89.99,
        'old_price' => 120,
        'image'     => '/assets/img/demo/girls-padded-hooded-jacket-light-pink.jpg',
        'sizes'     => ['6Y','8Y','10Y','12Y','14Y','16Y'],
    ],
    [
        'name'      => 'سترة بناتية مبطنة مع طاقية لون أسود (موديل 2)',
        'slug'      => 'girls-padded-hooded-jacket-black-2',
        'price'     => 89.99,
        'old_price' => 120,
        'image'     => '/assets/img/demo/girls-padded-hooded-jacket-black-2.jpg',
        'sizes'     => ['6Y','8Y','10Y','12Y','14Y','16Y'],
    ],
    [
        'name'      => 'سترة للأطفال مبطنة مع طاقية لون أسود',
        'slug'      => 'kids-padded-hooded-jacket-black',
        'price'     => 89.99,
        'old_price' => 120,
        'image'     => '/assets/img/demo/kids-padded-hooded-jacket-black.jpg',
        'sizes'     => ['4Y','6Y','8Y','10Y','12Y','14Y'],
    ],
    [
        'name'      => 'جاكيت فوتر للأطفال لون زهري (بيبي)',
        'slug'      => 'baby-fleece-hoodie-pink',
        'price'     => 15.99,
        'old_price' => 20,
        'image'     => '/assets/img/demo/baby-fleece-hoodie-pink.jpg',
        'sizes'     => ['12M','18M','24M'],
    ],
    [
        'name'      => 'جاكيت ولادي لون بني وبيج',
        'slug'      => 'boys-jacket-brown-beige',
        'price'     => 19.99,
        'old_price' => 50,
        'image'     => '/assets/img/demo/boys-jacket-brown-beige.jpg',
        'sizes'     => ['12-13Y'],
    ],
    [
        'name'      => 'سترة بناتية مبطنة مع طاقية لون زهري',
        'slug'      => 'girls-padded-hooded-jacket-pink',
        'price'     => 89.99,
        'old_price' => 120,
        'image'     => '/assets/img/demo/girls-padded-hooded-jacket-pink.jpg',
        'sizes'     => ['6Y','8Y','10Y','12Y','14Y','16Y'],
    ],
    [
        'name'      => 'سترة بناتية مبطنة مع طاقية لون أحمر',
        'slug'      => 'girls-padded-hooded-jacket-red',
        'price'     => 89.99,
        'old_price' => 120,
        'image'     => '/assets/img/demo/girls-padded-hooded-jacket-red.jpg',
        'sizes'     => ['6Y','8Y','10Y','12Y','14Y','16Y'],
    ],
],







'clothing/kids/bottoms' => [
    [
        'name'      => 'بنطال جينز للأطفال لون أزرق غامق',
        'slug'      => 'kids-jeans-dark-blue',
        'price'     => 49.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/kids-jeans-dark-blue.jpg',
        'sizes'     => ['9Y','11Y','13Y'],
    ],
    [
        'name'      => 'بنطال بناتي مخمل خصر مطاطي لون فوشي',
        'slug'      => 'girls-velvet-pants-fuchsia',
        'price'     => 19.99,
        'old_price' => 30,
        'image'     => '/assets/img/demo/girls-velvet-pants-fuchsia.jpg',
        'sizes'     => ['5Y','6Y','8Y'],
    ],
    [
        'name'      => 'بنطال بناتي مخمل خصر مطاطي لون بيج',
        'slug'      => 'girls-velvet-pants-beige',
        'price'     => 19.99,
        'old_price' => 30,
        'image'     => '/assets/img/demo/girls-velvet-pants-beige.jpg',
        'sizes'     => ['3Y','4Y','6Y'],
    ],
    [
        'name'      => 'بنطال للأطفال لون بني',
        'slug'      => 'kids-pants-brown',
        'price'     => 49.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/kids-pants-brown.jpg',
        'sizes'     => ['10Y'],
    ],
    [
        'name'      => 'بنطال للأطفال لون أسود',
        'slug'      => 'kids-pants-black',
        'price'     => 49.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/kids-pants-black.jpg',
        'sizes'     => ['8Y'],
    ],
    // ضيف/استبدل تحت 'clothing/kids/bottoms'
[
    'name'      => 'بنطال جينز للأطفال لون أزرق غامق',
    'slug'      => 'kids-jeans-dark-blue-9y',
    'price'     => 49.99,
    'old_price' => 100,
    'image'     => '/assets/img/demo/kids-jeans-dark-blue-9y.jpg',
    'sizes'     => ['9Y'],
],
[
    'name'      => 'بنطال جينز للأطفال لون أزرق غامق',
    'slug'      => 'kids-jeans-dark-blue-11y',
    'price'     => 49.99,
    'old_price' => 100,
    'image'     => '/assets/img/demo/kids-jeans-dark-blue-11y.jpg',
    'sizes'     => ['11Y'],
],
[
    'name'      => 'بنطال جينز للأطفال لون أزرق غامق',
    'slug'      => 'kids-jeans-dark-blue-13y',
    'price'     => 49.99,
    'old_price' => 100,
    'image'     => '/assets/img/demo/kids-jeans-dark-blue-13y.jpg',
    'sizes'     => ['13Y'],
],

],






'clothing/kids/underwear' => [
    [
        'name'      => 'طقم داخلي شبّاح + شورت للأطفال لون بني',
        'slug'      => 'kids-underwear-set-brown',
        'price'     => 9.99,
        'old_price' => 20,
        'image'     => '/assets/img/demo/kids-underwear-set-brown.jpg',
        'sizes'     => ['2-3Y'],
    ],
    [
        'name'      => 'طقم داخلي شبّاح + شورت للأطفال لون أصفر',
        'slug'      => 'kids-underwear-set-yellow',
        'price'     => 9.99,
        'old_price' => 20,
        'image'     => '/assets/img/demo/kids-underwear-set-yellow.jpg',
        'sizes'     => ['2-3Y'],
    ],
    [
        'name'      => 'طقم داخلي شبّاح + شورت للأطفال لون أخضر مشجّر',
        'slug'      => 'kids-underwear-set-green-floral',
        'price'     => 9.99,
        'old_price' => 20,
        'image'     => '/assets/img/demo/kids-underwear-set-green-floral.jpg',
        'sizes'     => ['2-3Y','1-2Y'],
    ],
    [
        'name'      => 'طقم داخلي شبّاح + شورت للأطفال لون زهري مشجّر',
        'slug'      => 'kids-underwear-set-pink-floral',
        'price'     => 9.99,
        'old_price' => 20,
        'image'     => '/assets/img/demo/kids-underwear-set-pink-floral.jpg',
        'sizes'     => ['1-2Y'],
    ],
    [
        'name'      => 'طقم داخلي شبّاح + شورت للأطفال لون رمادي بحافة صفراء',
        'slug'      => 'kids-underwear-set-grey-yellow-trim',
        'price'     => 9.99,
        'old_price' => 20,
        'image'     => '/assets/img/demo/kids-underwear-set-grey-yellow-trim.jpg',
        'sizes'     => ['2-3Y'],
    ],
    [
        'name'      => 'طقم داخلي شبّاح + شورت للأطفال لون أزرق',
        'slug'      => 'kids-underwear-set-blue',
        'price'     => 9.99,
        'old_price' => 20,
        'image'     => '/assets/img/demo/kids-underwear-set-blue.jpg',
        'sizes'     => ['1-2Y'],
    ],
    [
        'name'      => 'طقم داخلي شبّاح + شورت للأطفال لون أحمر',
        'slug'      => 'kids-underwear-set-red',
        'price'     => 9.99,
        'old_price' => 20,
        'image'     => '/assets/img/demo/kids-underwear-set-red.jpg',
        'sizes'     => ['2-3Y'],
    ],
    [
        'name'      => 'طقم داخلي شبّاح + شورت للأطفال لون تركواز',
        'slug'      => 'kids-underwear-set-turquoise',
        'price'     => 9.99,
        'old_price' => 20,
        'image'     => '/assets/img/demo/kids-underwear-set-turquoise.jpg',
        'sizes'     => ['1-2Y'],
    ],
],


'clothing/kids/blankets' => [
    [
        'name'      => 'حرام لِبيبي لون زهري 100×130 سم',
        'slug'      => 'baby-blanket-pink-100x130',
        'price'     => 44.00,
        'old_price' => 60,
        'image'     => '/assets/img/demo/baby-blanket-pink-100x130.jpg',
        'sizes'     => ['One Size'],
    ],
    [
        'name'      => 'حرام لِبيبي لون أزرق 100×130 سم',
        'slug'      => 'baby-blanket-blue-100x130',
        'price'     => 44.00,
        'old_price' => 60,
        'image'     => '/assets/img/demo/baby-blanket-blue-100x130.jpg',
        'sizes'     => ['One Size'],
    ],
    [
        'name'      => 'حرام لِبيبي لون رمادي 100×130 سم',
        'slug'      => 'baby-blanket-grey-100x130',
        'price'     => 44.00,
        'old_price' => 60,
        'image'     => '/assets/img/demo/baby-blanket-grey-100x130.jpg',
        'sizes'     => ['One Size'],
    ],
    [
        'name'      => 'حرام بيبي لون رمادي',
        'slug'      => 'baby-blanket-grey',
        'price'     => 22.00,
        'old_price' => 30,
        'image'     => '/assets/img/demo/baby-blanket-grey-small.jpg',
        'sizes'     => ['One Size'],
    ],
    [
        'name'      => 'حرام فُو بيبي حجم 100×75 سم لون أزرق فاتح',
        'slug'      => 'faux-baby-blanket-100x75-light-blue',
        'price'     => 28.99,
        'old_price' => 40,
        'image'     => '/assets/img/demo/faux-baby-blanket-100x75-light-blue.jpg',
        'sizes'     => ['One Size'],
    ],
    [
        'name'      => 'حرام فُو بيبي حجم 100×75 سم لون رمادي',
        'slug'      => 'faux-baby-blanket-100x75-grey',
        'price'     => 28.99,
        'old_price' => 40,
        'image'     => '/assets/img/demo/faux-baby-blanket-100x75-grey.jpg',
        'sizes'     => ['One Size'],
    ],
    [
        'name'      => 'حرام فُو بيبي حجم 100×75 سم لون أوف وايت',
        'slug'      => 'faux-baby-blanket-100x75-offwhite',
        'price'     => 28.99,
        'old_price' => 40,
        'image'     => '/assets/img/demo/faux-baby-blanket-100x75-offwhite.jpg',
        'sizes'     => ['One Size'],
    ],
    [
        'name'      => 'حرام لِبيبي لون أصفر 100×130 سم',
        'slug'      => 'baby-blanket-yellow-100x130',
        'price'     => 44.00,
        'old_price' => 60,
        'image'     => '/assets/img/demo/baby-blanket-yellow-100x130.jpg',
        'sizes'     => ['One Size'],
    ],
],



'perfumes/men' => [
    [
        'name'      => 'عطر داريج من الرصاصي للرجال - 100 مل',
        'slug'      => 'rasasi-dareej-men-100ml',
        'brand'     => 'Rasasi',
        'price'     => 54.99,
        'old_price' => 120,
        'image'     => '/assets/img/demo/rasasi-dareej-men-100.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'عطر سيلفر سينت إنتنس من جاكومو - 100 مل',
        'slug'      => 'silver-scent-intense-100ml',
        'brand'     => 'Jacques Bogart',
        'price'     => 129.99,
        'old_price' => 160,
        'image'     => '/assets/img/demo//silver-scent-intense.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'عطر هوكد بور هوم من رو بروكا للرجال - 100 مل',
        'slug'      => 'hooked-pour-homme-100ml',
        'brand'     => 'Rue Broca',
        'price'     => 79.99,
        'old_price' => 150,
        'image'     => '/assets/img/demo/hooked-pour-homme-100.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'عطر نوتيكا فويّاج للرجال - 100 مل',
        'slug'      => 'nautica-voyage-100ml',
        'brand'     => 'Nautica',
        'price'     => 79.99,
        'old_price' => 120,
        'image'     => '/assets/img/demo/nautica-voyage-100.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'Club De Nuit Intense Man EDP 200ml by Armaf',
        'slug'      => 'club-de-nuit-intense-man-edp-200',
        'brand'     => 'Armaf',
        'price'     => 239.99,
        'old_price' => 270,
        'image'     => '/assets/img/demo/club-de-nuit-intense-man-200.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'عطر أجوود EDP للجنسين - 100 مل',
        'slug'      => 'ajood-edp-100ml',
        'brand'     => 'Lattafa',
        'price'     => 54.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/ajood-100.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'عطر أواان EDP للجنسين - 100 مل',
        'slug'      => 'awaan-edp-100ml',
        'brand'     => 'Lattafa',
        'price'     => 54.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/awaan-100.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'عطر مهرجان EDP للجنسين - 100 مل',
        'slug'      => 'mahrajan-edp-100ml',
        'brand'     => 'Lattafa',
        'price'     => 64.99,
        'old_price' => 120,
        'image'     => '/assets/img/demo/mahrajan-100.jpg',
        'sizes'     => [],
    ],
 ],








// ===== معطرات الجو / Air
'perfumes/air' => [
    [
        'name'      => 'معطر جو عِبدان برائحة اللافندر - حجم 25 مل',
        'slug'      => 'room-freshener-abdan-lavender-25ml',
        'brand'     => 'Abdan',
        'price'     => 5.99,
        'old_price' => 9,
        'image'     => '/assets/img/demo/abdan-lavender-25.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'معطر جو عِبدان برائحة المحيط اوشنين - حجم 25 مل',
        'slug'      => 'room-freshener-abdan-ocean-25ml',
        'brand'     => 'Abdan',
        'price'     => 5.99,
        'old_price' => 9,
        'image'     => '/assets/img/demo/abdan-ocean-25.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'معطر جو عِبدان برائحة الليمون - حجم 25 مل',
        'slug'      => 'room-freshener-abdan-lemon-25ml',
        'brand'     => 'Abdan',
        'price'     => 5.99,
        'old_price' => 9,
        'image'     => '/assets/img/demo/abdan-lemon-25.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'معطر جو عِبدان برائحة روز - حجم 25 مل',
        'slug'      => 'room-freshener-abdan-rose-25ml',
        'brand'     => 'Abdan',
        'price'     => 5.99,
        'old_price' => 9,
        'image'     => '/assets/img/demo/abdan-rose-25.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'معطر جو عِبدان برائحة الياسمين - حجم 25 مل',
        'slug'      => 'room-freshener-abdan-jasmine-25ml',
        'brand'     => 'Abdan',
        'price'     => 5.99,
        'old_price' => 9,
        'image'     => '/assets/img/demo/abdan-jasmine-25.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'معطر جو عِبدان برائحة ليلي - حجم 25 مل',
        'slug'      => 'room-freshener-abdan-lily-25ml',
        'brand'     => 'Abdan',
        'price'     => 5.99,
        'old_price' => 9,
        'image'     => '/assets/img/demo/abdan-lily-25.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'معطر جو BLX - كول اكس 250 مل',
        'slug'      => 'blx-cool-x-250ml',
        'brand'     => 'BLX',
        'price'     => 19.99,
        'old_price' => 25,
        'image'     => '/assets/img/demo/blx-coolx-250.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'برنر بروسلاين مع 3 علب زيوت عطرية برائحة الياسمين',
        'slug'      => 'porcelain-burner-3oils-jasmine',
        'brand'     => 'Generic',
        'price'     => 33.00,
        'old_price' => 50,
        'image'     => '/assets/img/demo/porcelain-burner-jasmine-set.jpg',
        'sizes'     => [],
    ],
],








// ===== عطور → بخور
'perfumes/bukhoor'=> [
    [
        'name'      => 'مكعبات مسك جامد ( 6 عدد ) + كيس من عبد الصمد القرشي',
        'slug'      => 'solid-musk-cubes-6-with-bag',
        'price'     => 99.99,
        'old_price' => 140,
        'image'     => '/assets/img/demo/solid-musk-cubes-6-with-bag.jpg',
        'sizes'     => [],
        'brand'     => 'Abdul Samad Al Qurashi',
    ],
    [
        'name'      => 'تبر العود 70 غرام + كيس من عبد الصمد القرشي',
        'slug'      => 'tibr-oud-70g-with-bag',
        'price'     => 99.99,
        'old_price' => 150,
        'image'     => '/assets/img/demo/tibr-oud-70g-with-bag.jpg',
        'sizes'     => [],
        'brand'     => 'Abdul Samad Al Qurashi',
    ],
    [
        'name'      => 'معمول بخور القرشي الفاخر 90 غرام + كيس من عبد الصمد',
        'slug'      => 'mahmool-bukhoor-90g-with-bag',
        'price'     => 149.99,
        'old_price' => 200,
        'image'     => '/assets/img/demo/mahmool-bukhoor-90g-with-bag.jpg',
        'sizes'     => [],
        'brand'     => 'Abdul Samad Al Qurashi',
    ],
],

// ===== عطور نسائية
'perfumes/women' => [
   [
        'name'      => 'Awaan EDP - للجنسين 100 مل',
        'slug'      => 'awaan-edp-100ml',
        'price'     => 54.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/awaan-edp-women-100.jpg',
        'brand'     => 'Lattafa',
        'sizes'     => [],
    ],
    [
        'name'      => 'Mahrajan EDP - للجنسين 100 مل',
        'slug'      => 'mahrajan-edp-100ml',
        'price'     => 64.99,
        'old_price' => 120,
        'image'     => '/assets/img/demo/mahrajan-edp-women-100.jpg',
        'brand'     => 'Lattafa',
        'sizes'     => [],
    ],
    [
        'name'      => 'Nexa Musee EDP By Rue Broca - for Womens 100 ML',
        'slug'      => 'nexa-musee-women-100ml',
        'price'     => 99.99,
        'old_price' => 150,
        'image'     => '/assets/img/demo/nexa-musee-women-100.jpg',
        'brand'     => 'Rue Broca',
        'sizes'     => [],
    ],
    [
        'name'      => 'عطر هوكد بور هوم من رو بروكا للنساء - 100 مل',
        'slug'      => 'hooked-pour-homme-women-100ml',
        'price'     => 99.99,
        'old_price' => 150,
        'image'     => '/assets/img/demo/hooked-women-100.jpg',
        'brand'     => 'Rue Broca',
        'sizes'     => [],
    ],
    [
        'name'      => 'Versace Crystal Noir للنساء 90 مل',
        'slug'      => 'versace-crystal-noir-90ml',
        'price'     => 219.99,
        'old_price' => 250,
        'image'     => '/assets/img/demo/versace-crystal-noir-90.jpg',
        'brand'     => 'Versace',
        'sizes'     => [],
    ],
    [
        'name'      => 'Lahdath EDP من لطافة للنساء 80 مل',
        'slug'      => 'lahdath-edp-women-80ml',
        'price'     => 64.99,
        'old_price' => 120,
        'image'     => '/assets/img/demo/lahdath-women-80.jpg',
        'brand'     => 'Lattafa',
        'sizes'     => [],
    ],
    [
        'name'      => 'Qissa من Paris Corner للنساء 100 مل',
        'slug'      => 'qissa-women-100ml',
        'price'     => 79.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/qissa-women-100.jpg',
        'brand'     => 'Paris Corner',
        'sizes'     => [],
    ],
     [
        'name'      => 'عطر وجود من لطافة وفخامة للنساء - 100 مل',
        'slug'      => 'hooked-pour-homme-women-100ml',
        'price'     => 99.99,
        'old_price' => 150,
        'image'     => '/assets/img/demo/hooked-women-1100.jpg',
        'brand'     => 'Rue Broca',
        'sizes'     => [],
    ],
    ],









    // ===== عطور الشعر
'perfumes/hair' => [
    [
        'name'      => 'Redist Hair Perfume Pink Sugar 50ml',
        'slug'      => 'redist-hair-perfume-pink-sugar-50ml',
        'price'     => 74.99,
        'old_price' => 90,
        'image'     => '/assets/img/demo/redist-hair-perfume-pink-sugar-50.jpg',
        'brand'     => 'Redist',
        'sizes'     => [],
    ],
    [
        'name'      => 'عطر شعر ميراى من ميريام مارفاز للنساء 160مل - Miray Hair Mist',
        'slug'      => 'miray-hair-mist-160ml',
        'price'     => 33.99,
        'old_price' => 50,
        'image'     => '/assets/img/demo/miray-hair-mist-160.jpg',
        'brand'     => 'Miray',
        'sizes'     => [],
    ],
    [
        'name'      => 'عطر شعر لالى من ميريام مارفاز للنساء 160مل - Lale Hair Mist',
        'slug'      => 'lale-hair-mist-160ml',
        'price'     => 33.99,
        'old_price' => 50,
        'image'     => '/assets/img/demo/lale-hair-mist-160.jpg',
        'brand'     => 'Lale',
        'sizes'     => [],
    ],
    [
        'name'      => 'زيت دباب قوي للشعر 130 مل + كيس من عبد الصمد القرشي',
        'slug'      => 'abdul-samad-qurashi-strong-hair-oil-130ml',
        'price'     => 79.99,
        'old_price' => 120,
        'image'     => '/assets/img/demo/asq-strong-hair-oil-130.jpg',
        'brand'     => 'Abdul Samad Al Qurashi',
        'sizes'     => [],
    ],
    [
        'name'      => 'REDIST hair perfume keratin love garden بالكراتين',
        'slug'      => 'redist-hair-perfume-keratin-love-garden',
        'price'     => 74.99,
        'old_price' => 90,
        'image'     => '/assets/img/demo/redist-keratin-love-garden-50.jpg',
        'brand'     => 'Redist',
        'sizes'     => [],
    ],
    [
        'name'      => 'REDIST hair perfume argan 50ml',
        'slug'      => 'redist-hair-perfume-argan-50ml',
        'price'     => 74.99,
        'old_price' => 90,
        'image'     => '/assets/img/demo/redist-hair-perfume-argan-50.jpg',
        'brand'     => 'Redist',
        'sizes'     => [],
    ],
    ],


    






'perfumes/body' => [
    [
        'name'      => 'لو سيبل بخّاخ مزيل عرق 150 مل',
        'slug'      => 'sebel-deo-spray-150',
        'price'     => 19.99,
        'old_price' => 60,
        'brand'     => 'Le Sebel',
        'image'     => '/assets/img/demo/sebel-deo-spray-150.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'لافلين رول مزيل عرق سبورت يدوم حتى 72 ساعة',
        'slug'      => 'lavilin-sport-72h',
        'price'     => 49.99,
        'old_price' => 80,
        'brand'     => 'Lavilin',
        'image'     => '/assets/img/demo/lavilin-sport-72h.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'معطر جسم good girl – Lovina',
        'slug'      => 'lovina-body-splash-good-girl',
        'price'     => 29.99,
        'old_price' => 40,
        'brand'     => 'Lovina',
        'image'     => '/assets/img/demo/lovina-good-girl.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'بخّاخ معطر للجسم ديليكيت من ميريام مارفاز – 200 مل',
        'slug'      => 'marfaz-delicate-body-200',
        'price'     => 7.99,
        'old_price' => 15,
        'brand'     => 'Miray Marfaz',
        'image'     => '/assets/img/demo/marfaz-delicate-200.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'مزيل عرق رول اون للسيدات – شمبانيا 60 مل (Jori)',
        'slug'      => 'jori-champagne-rollon-60',
        'price'     => 8.99,
        'old_price' => 15,
        'brand'     => 'Jori',
        'image'     => '/assets/img/demo/jori-champagne-rollon-60.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'مزيل عرق رول اون – Core Modern للسيدات 60 مل (Jori)',
        'slug'      => 'jori-core-modern-rollon-60',
        'price'     => 8.99,
        'old_price' => 15,
        'brand'     => 'Jori',
        'image'     => '/assets/img/demo/jori-core-modern-rollon-60.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'معطر هواء حبيبات – ام اس في (ألوان متعددة)',
        'slug'      => 'msv-air-beads-mix',
        'price'     => 19.99,
        'old_price' => 30,
        'brand'     => 'MSV',
        'image'     => '/assets/img/demo/msv-air-beads.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'إسبره موس للتسمير الذاتي – SELF TAN MOUSSE',
        'slug'      => 'self-tan-mousse',
        'price'     => 37.99,
        'old_price' => 70,
        'brand'     => 'Modern',
        'image'     => '/assets/img/demo/self-tan-mousse.jpg',
        'sizes'     => [],
    ],
    ],







// ===== مكياج الشفاه - Lips
'beauty/makeup/lips' => [
    [
        'name'      => 'Pierre Cardin Staylong - Lipcolor Kissproof 326 Blood',
        'slug'      => 'pierre-cardin-kissproof-326-blood',
        'price'     => 22.00,
        'old_price' => 40,
        'image'     => '/assets/img/demo/pierre-cardin-kissproof-326-blood.jpg',
        'brand'     => 'Pierre Cardin',
        'sizes'     => [],
    ],
    [
        'name'      => 'Pierre Cardin Staylong - Lipcolor Kissproof 336',
        'slug'      => 'pierre-cardin-kissproof-336',
        'price'     => 22.00,
        'old_price' => 40,
        'image'     => '/assets/img/demo/pierre-cardin-kissproof-336.jpg',
        'brand'     => 'Pierre Cardin',
        'sizes'     => [],
    ],
    [
        'name'      => 'Pierre Cardin Staylong - Lipcolor Kissproof 356',
        'slug'      => 'pierre-cardin-kissproof-356',
        'price'     => 22.00,
        'old_price' => 40,
        'image'     => '/assets/img/demo/pierre-cardin-kissproof-356.jpg',
        'brand'     => 'Pierre Cardin',
        'sizes'     => [],
    ],
    [
        'name'      => 'Pierre Cardin Staylong - Lipcolor Kissproof 353',
        'slug'      => 'pierre-cardin-kissproof-353',
        'price'     => 22.00,
        'old_price' => 40,
        'image'     => '/assets/img/demo/pierre-cardin-kissproof-353.jpg',
        'brand'     => 'Pierre Cardin',
        'sizes'     => [],
    ],
    [
        'name'      => 'Pierre Cardin Staylong - Lipcolor Kissproof 360',
        'slug'      => 'pierre-cardin-kissproof-360',
        'price'     => 22.00,
        'old_price' => 40,
        'image'     => '/assets/img/demo/pierre-cardin-kissproof-360.jpg',
        'brand'     => 'Pierre Cardin',
        'sizes'     => [],
    ],
    [
        'name'      => 'Pierre Cardin Staylong - Lipcolor Kissproof 350 Coral',
        'slug'      => 'pierre-cardin-kissproof-350-coral',
        'price'     => 22.00,
        'old_price' => 40,
        'image'     => '/assets/img/demo/pierre-cardin-kissproof-350-coral.jpg',
        'brand'     => 'Pierre Cardin',
        'sizes'     => [],
    ],
    [
        'name'      => 'Pierre Cardin Staylong - Lipcolor Kissproof 348',
        'slug'      => 'pierre-cardin-kissproof-348',
        'price'     => 22.00,
        'old_price' => 40,
        'image'     => '/assets/img/demo/pierre-cardin-kissproof-348.jpg',
        'brand'     => 'Pierre Cardin',
        'sizes'     => [],
    ],
    [
        'name'      => 'Pierre Cardin Staylong - Lipcolor Kissproof 347',
        'slug'      => 'pierre-cardin-kissproof-347',
        'price'     => 22.00,
        'old_price' => 40,
        'image'     => '/assets/img/demo/pierre-cardin-kissproof-347.jpg',
        'brand'     => 'Pierre Cardin',
        'sizes'     => [],
    ],
    ],









// ===== مكياج العيون (Eyes)
'beauty/makeup/eyes' => [
    [
        'name'      => 'pierre cardin paris palette — eyeshadow (1)',
        'slug'      => 'pc-eyeshadow-palette-1',
        'brand'     => 'Pierre Cardin',
        'price'     => 49.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/pc-eyeshadow-palette-1.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'pierre cardin paris palette — eyeshadow (2)',
        'slug'      => 'pc-eyeshadow-palette-2',
        'brand'     => 'Pierre Cardin',
        'price'     => 49.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/pc-eyeshadow-palette-2.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'pierre cardin paris palette — eyeshadow (3)',
        'slug'      => 'pc-eyeshadow-palette-3',
        'brand'     => 'Pierre Cardin',
        'price'     => 49.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/pc-eyeshadow-palette-3.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'pierre cardin paris palette — eyeshadow (4)',
        'slug'      => 'pc-eyeshadow-palette-4',
        'brand'     => 'Pierre Cardin',
        'price'     => 49.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/pc-eyeshadow-palette-4.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'Quiz EYELINER long lasting ultra black 2.5 ml',
        'slug'      => 'quiz-eyeliner-ultra-black-2-5',
        'brand'     => 'Quiz',
        'price'     => 33.00,
        'old_price' => 40,
        'image'     => '/assets/img/demo/quiz-eyeliner-ultra-black-2-5.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'ماسكارا Level Up من بير كاردان',
        'slug'      => 'pc-level-up-mascara',
        'brand'     => 'Pierre Cardin',
        'price'     => 23.99,
        'old_price' => 40,
        'image'     => '/assets/img/demo/pc-level-up-mascara.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'Quiz — هواء جيباتس (جليتر عيون) — 4 ألوان',
        'slug'      => 'quiz-eyes-glitter-4colors',
        'brand'     => 'Quiz',
        'price'     => 19.99,
        'old_price' => 30,
        'image'     => '/assets/img/demo/quiz-eyes-glitter-4.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'pierre cardin paris palette — eyeshadow (5)',
        'slug'      => 'pc-eyeshadow-palette-5',
        'brand'     => 'Pierre Cardin',
        'price'     => 49.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/pc-eyeshadow-palette-5.jpg',
        'sizes'     => [],
    ],
    ],







// ===== مكياج: Face
'beauty/makeup/face' => [
    [
        'name'      => 'pierre cardin paris cashmere blush on 357 rosy plum',
        'slug'      => 'pierre-cardin-cashmere-blush-357-rosy-plum',
        'price'     => 24.99,
        'old_price' => 50,
        'image'     => '/assets/img/demo/face-cashmere-blush-357-rosy-plum.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'photo filter liquid concealer 13ml shade tan 823',
        'slug'      => 'photo-filter-liquid-concealer-13ml-tan-823',
        'price'     => 29.99,
        'old_price' => 60,
        'image'     => '/assets/img/demo/face-photo-filter-concealer-tan-823.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'pierre cardin paris cashmere blush on 362 melon',
        'slug'      => 'pierre-cardin-cashmere-blush-362-melon',
        'price'     => 24.99,
        'old_price' => 50,
        'image'     => '/assets/img/demo/face-cashmere-blush-362-melon.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'pierre cardin paris cashmere blush on 358 flamingo pink',
        'slug'      => 'pierre-cardin-cashmere-blush-358-flamingo-pink',
        'price'     => 24.99,
        'old_price' => 50,
        'image'     => '/assets/img/demo/face-cashmere-blush-358-flamingo-pink.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'Micellar purifying jelly water green tea & bamboo charcoal',
        'slug'      => 'micellar-purifying-jelly-water-green-tea-bamboo-charcoal',
        'price'     => 34.99,
        'old_price' => 50,
        'image'     => '/assets/img/demo/face-micellar-purifying-jelly-water.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'pierre cardin paris cashmere blush on 361 mocha',
        'slug'      => 'pierre-cardin-cashmere-blush-361-mocha',
        'price'     => 24.99,
        'old_price' => 50,
        'image'     => '/assets/img/demo/face-cashmere-blush-361-mocha.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'pierre cardin paris cashmere blush on 363 peach',
        'slug'      => 'pierre-cardin-cashmere-blush-363-peach',
        'price'     => 24.99,
        'old_price' => 50,
        'image'     => '/assets/img/demo/face-cashmere-blush-363-peach.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'pierre cardin paris cashmere blush on 359 spring rose',
        'slug'      => 'pierre-cardin-cashmere-blush-359-spring-rose',
        'price'     => 24.99,
        'old_price' => 50,
        'image'     => '/assets/img/demo/face-cashmere-blush-359-spring-rose.jpg',
        'sizes'     => [],
    ],

    ],










// ===== مكياج - أظافر (Nails)
'beauty/makeup/nails' => [
    [
        'name'      => 'طلاء أظافر (مناكير) من دلفي درجة 80098 حجم 15مل - Delfy',
        'slug'      => 'delfy-nail-polish-80098-15ml',
        'price'     => 13.99,
        'old_price' => 50,
        'brand'     => 'Delfy',
        'image'     => '/assets/img/demo/delfy-80098-15ml.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'GABRINI PACIFIC NAILPOLISH-89',
        'slug'      => 'gabrini-pacific-nailpolish-89',
        'price'     => 4.99,
        'old_price' => 8,
        'brand'     => 'Gabrini',
        'image'     => '/assets/img/demo/gabrini-pacific-89.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'Nail Polish Remover Acetone مزيل طلاء الأظافر - 200 ml (ليمون)',
        'slug'      => 'pierre-cardin-remover-200ml-lemon',
        'price'     => 15.99,
        'old_price' => 30,
        'brand'     => 'Pierre Cardin',
        'image'     => '/assets/img/demo/remover-lemon-200ml.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'Nail Polish Remover Acetone مزيل طلاء الأظافر - 200 ml (نعناع)',
        'slug'      => 'pierre-cardin-remover-200ml-mint',
        'price'     => 15.99,
        'old_price' => 30,
        'brand'     => 'Pierre Cardin',
        'image'     => '/assets/img/demo/remover-mint-200ml.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'طلاء أظافر (مناكير) من دلفي درجة 1067A حجم 15مل - Delfy',
        'slug'      => 'delfy-nail-polish-1067a-15ml',
        'price'     => 13.99,
        'old_price' => 50,
        'brand'     => 'Delfy',
        'image'     => '/assets/img/demo/delfy-1067a-15ml.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'طلاء أظافر (مناكير) من دلفي درجة 1071A حجم 15مل - Delfy',
        'slug'      => 'delfy-nail-polish-1071a-15ml',
        'price'     => 13.99,
        'old_price' => 50,
        'brand'     => 'Delfy',
        'image'     => '/assets/img/demo/delfy-1071a-15ml.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'طلاء أظافر (مناكير) من دلفي درجة 1072A حجم 15مل - Delfy',
        'slug'      => 'delfy-nail-polish-1072a-15ml',
        'price'     => 13.99,
        'old_price' => 50,
        'brand'     => 'Delfy',
        'image'     => '/assets/img/demo/delfy-1072a-15ml.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'طلاء أظافر (مناكير) من دلفي درجة 7007B حجم 15مل - Delfy',
        'slug'      => 'delfy-nail-polish-7007b-15ml',
        'price'     => 13.99,
        'old_price' => 50,
        'brand'     => 'Delfy',
        'image'     => '/assets/img/demo/delfy-7007b-15ml.jpg',
        'sizes'     => [],
    ],
    ],








// ===== العناية بالبشرة (MART HERB) =====
'beauty/herb/skin' => [
    [
        'name'      => 'Sinoz Pure Cica Serum 30ml',
        'slug'      => 'sinoz-pure-cica-serum-30ml',
        'price'     => 84.99,
        'old_price' => 120,
        'image'     => '/assets/img/demo/sinoz-pure-cica-serum-30ml.jpg',
        'brand'     => 'Sinoz',
        'sizes'     => [],
    ],
    [
        'name'      => 'Sinoz Retinol Lift Up Serum 30ml',
        'slug'      => 'sinoz-retinol-liftup-serum-30ml',
        'price'     => 84.99,
        'old_price' => 120,
        'image'     => '/assets/img/demo/sinoz-retinol-liftup-serum-30ml.jpg',
        'brand'     => 'Sinoz',
        'sizes'     => [],
    ],
    [
        'name'      => 'Sinoz Sunscreen SPF50+ 50ml',
        'slug'      => 'sinoz-sunscreen-spf50-50ml',
        'price'     => 84.99,
        'old_price' => 120,
        'image'     => '/assets/img/demo/sinoz-sunscreen-spf50-50ml.jpg',
        'brand'     => 'Sinoz',
        'sizes'     => [],
    ],
    [
        'name'      => 'Sinoz Pink Touch Color Correcting Cream SPF50 50ml',
        'slug'      => 'sinoz-pink-touch-cc-spf50-50ml',
        'price'     => 84.99,
        'old_price' => 120,
        'image'     => '/assets/img/demo/sinoz-pink-touch-cc-spf50-50ml.jpg',
        'brand'     => 'Sinoz',
        'sizes'     => [],
    ],
    [
        'name'      => 'Sinobaby Diaper Rash Cream with Zinc Oxide 50ml',
        'slug'      => 'sinobaby-diaper-rash-cream-50ml',
        'price'     => 23.99,
        'old_price' => 30,
        'image'     => '/assets/img/demo/sinobaby-diaper-rash-cream-50ml.jpg',
        'brand'     => 'Sinobaby',
        'sizes'     => [],
    ],
    [
        'name'      => 'فرشاة تنظيف الوجه سيليكون شكل قلب',
        'slug'      => 'silicone-heart-face-brush',
        'price'     => 4.99,
        'old_price' => 10,
        'image'     => '/assets/img/demo/silicone-heart-face-brush.jpg',
        'brand'     => 'Generic',
        'sizes'     => [],
    ],
    [
        'name'      => 'Sinoz Beauty Scented Body Oil 250ml',
        'slug'      => 'sinoz-beauty-scented-body-oil-250ml',
        'price'     => 74.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/sinoz-beauty-body-oil-250ml.jpg',
        'brand'     => 'Sinoz',
        'sizes'     => [],
    ],
    [
        'name'      => 'Sinoz Glow Tonic 5% PHA 200ml',
        'slug'      => 'sinoz-glow-tonic-5-pha-200ml',
        'price'     => 64.99,
        'old_price' => 90,
        'image'     => '/assets/img/demo/sinoz-glow-tonic-5-pha-200ml.jpg',
        'brand'     => 'Sinoz',
        'sizes'     => [],
    ],
    ],










// ===== عناية الجسم (MART HERB / Body)
'beauty/herb/body' => [
    [
        'name'      => 'طقم شفرات حلاقة 5 قطع Simply Max للجسم',
        'slug'      => 'simply-max-razors-5pcs',
        'price'     => 7.99,
        'old_price' => 15,
        'image'     => '/assets/img/demo/simply-max-razors-5pcs.jpg',
        'brand'     => 'Simply Max',
        'sizes'     => [],
    ],
    [
        'name'      => 'منظار تنظيف الأذن – طقم أدوات احترافية (Y39)',
        'slug'      => 'ear-cleaning-kit-y39',
        'price'     => 69.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/ear-cleaning-kit-y39.jpg',
        'brand'     => 'Generic',
        'sizes'     => [],
    ],
    [
        'name'      => 'بكج العناية بالقدم – 4 قطع (ملح، فوار، مقشّر…)',
        'slug'      => 'foot-care-pack-4pcs',
        'price'     => 119.99,
        'old_price' => 150,
        'image'     => '/assets/img/demo/foot-care-pack-4pcs.jpg',
        'brand'     => 'MART HERB',
        'sizes'     => [],
    ],
    [
        'name'      => 'بكج العناية بالشفاه – 2 قطعة (مقشّر + مرطّب)',
        'slug'      => 'lip-care-pack-2pcs',
        'price'     => 49.99,
        'old_price' => 55,
        'image'     => '/assets/img/demo/lip-care-pack-2pcs.jpg',
        'brand'     => 'MART HERB',
        'sizes'     => [],
    ],
    [
        'name'      => 'ليفة استحمام مع صابونة برائحة خشبية للرجال',
        'slug'      => 'woody-men-bath-salt-pouch',
        'price'     => 17.99,
        'old_price' => 20,
        'image'     => '/assets/img/demo/woody-men-bath-salt-pouch.jpg',
        'brand'     => 'Balmy',
        'sizes'     => [],
    ],
    [
        'name'      => 'ليفة استحمام مع صابونة برائحة اللافندر',
        'slug'      => 'lavender-bath-salt-pouch',
        'price'     => 17.99,
        'old_price' => 20,
        'image'     => '/assets/img/demo/lavender-bath-salt-pouch.jpg',
        'brand'     => 'Balmy',
        'sizes'     => [],
    ],
    [
        'name'      => 'طقم شفرات سيدات للجسم Max 6 (6 قطع)',
        'slug'      => 'women-body-razors-max6-6pcs',
        'price'     => 7.99,
        'old_price' => 15,
        'image'     => '/assets/img/demo/women-body-razors-max6-6pcs.jpg',
        'brand'     => 'Max',
        'sizes'     => [],
    ],
    [
        'name'      => 'طقم شفرات حلاقة Triple blades (4 قطع) Pacehion',
        'slug'      => 'pacehion-triple-blades-4pcs',
        'price'     => 3.99,
        'old_price' => 10,
        'image'     => '/assets/img/demo/pacehion-triple-blades-4pcs.jpg',
        'brand'     => 'Pacehion',
        'sizes'     => [],
    ],
    ],










// ===== MART HERB — Hair (أصباغ شعر Biomagic)
'beauty/herb/hair' => [
    [
        'name'      => 'Biomagic Hair Color Cream 77.07 Natural Brown Blonde',
        'slug'      => 'biomagic-77-07-natural-brown-blonde',
        'price'     => 39.99,
        'old_price' => 50,
        'brand'     => 'BioMagic',
        'image'     => '/assets/img/demo/biomagic-77-07.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'Biomagic Hair Color Cream 6.78 Deep Dark Blonde Beige',
        'slug'      => 'biomagic-6-78-deep-dark-blonde-beige',
        'price'     => 39.99,
        'old_price' => 50,
        'brand'     => 'BioMagic',
        'image'     => '/assets/img/demo/biomagic-6-78.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'Biomagic Hair Color Cream 7.72 Beige Blonde',
        'slug'      => 'biomagic-7-72-beige-blonde',
        'price'     => 39.99,
        'old_price' => 50,
        'brand'     => 'BioMagic',
        'image'     => '/assets/img/demo/biomagic-7-72.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'Biomagic Hair Color Cream 77.33 Deep Golden Blond',
        'slug'      => 'biomagic-77-33-deep-golden-blond',
        'price'     => 39.99,
        'old_price' => 50,
        'brand'     => 'BioMagic',
        'image'     => '/assets/img/demo/biomagic-77-33.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'Biomagic Hair Color Cream 4 Brown',
        'slug'      => 'biomagic-4-brown',
        'price'     => 39.99,
        'old_price' => 50,
        'brand'     => 'BioMagic',
        'image'     => '/assets/img/demo/biomagic-4-00.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'Biomagic Hair Color Cream 8.00 Light Blonde',
        'slug'      => 'biomagic-8-00-light-blonde',
        'price'     => 39.99,
        'old_price' => 50,
        'brand'     => 'BioMagic',
        'image'     => '/assets/img/demo/biomagic-8-00.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'Biomagic Hair Color Cream 7.00 Blonde',
        'slug'      => 'biomagic-7-00-blonde',
        'price'     => 39.99,
        'old_price' => 50,
        'brand'     => 'BioMagic',
        'image'     => '/assets/img/demo/biomagic-7-00.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'Biomagic Hair Color Cream 6 Dark Blond',
        'slug'      => 'biomagic-6-00-dark-blond',
        'price'     => 39.99,
        'old_price' => 50,
        'brand'     => 'BioMagic',
        'image'     => '/assets/img/demo/biomagic-6-00.jpg',
        'sizes'     => [],
    ],
    ],

// ========== MART HERB → Oral ==========
'beauty/herb/oral' => [
    [
        'name'      => 'LACALUT INTER DENTAL - BRUSH S (أحمر)',
        'slug'      => 'lacalut-interdental-brush-s',
        'price'     => 20.99,
        'old_price' => 25,
        'brand'     => 'Lacalut',
        'image'     => '/assets/img/demo/lacalut-interdental-brush-s.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'LACALUT INTER DENTAL - BRUSH M (أزرق)',
        'slug'      => 'lacalut-interdental-brush-m',
        'price'     => 20.99,
        'old_price' => 25,
        'brand'     => 'Lacalut',
        'image'     => '/assets/img/demo/lacalut-interdental-brush-m.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'فرشاة أسنان للأطفال 360° درجة',
        'slug'      => 'degree-baby-toothbrush-360',
        'price'     => 6.99,
        'old_price' => 15,
        'brand'     => 'Generic',
        'image'     => '/assets/img/demo/baby-toothbrush-360.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'طقم فرشاة أسنان 12 قطعة',
        'slug'      => 'toothbrush-12-pcs-set',
        'price'     => 8.99,
        'old_price' => 20,
        'brand'     => 'Generic',
        'image'     => '/assets/img/demo/toothbrush-12-pcs-set.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'LACALUT EXTRA SENSITIVE - PASTE (معجون أسنان)',
        'slug'      => 'lacalut-extra-sensitive-paste',
        'price'     => 29.99,
        'old_price' => 35,
        'brand'     => 'Lacalut',
        'image'     => '/assets/img/demo/lacalut-extra-sensitive-paste.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'LACALUT FLORA - MOUTHWASH 300ml',
        'slug'      => 'lacalut-flora-mouthwash-300ml',
        'price'     => 35.99,
        'old_price' => 45,
        'brand'     => 'Lacalut',
        'image'     => '/assets/img/demo/lacalut-flora-mouthwash-300ml.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'LACALUT SENSITIVE Extra Soft - TOOTHBRUSH',
        'slug'      => 'lacalut-sensitive-extra-soft-toothbrush',
        'price'     => 18.99,
        'old_price' => 25,
        'brand'     => 'Lacalut',
        'image'     => '/assets/img/demo/lacalut-sensitive-extra-soft.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'LACALUT INTER DENTAL - BRUSH XXS (وردي)',
        'slug'      => 'lacalut-interdental-brush-xxs',
        'price'     => 20.99,
        'old_price' => 25,
        'brand'     => 'Lacalut',
        'image'     => '/assets/img/demo/lacalut-interdental-brush-xxs.jpg',
        'sizes'     => [],
    ],
    ],







    // ===== قسم الجمال > العناية بالأظافر
'beauty/herb/nailcare' => [
    [
        'name'       => 'ماكينة تجفيف مناكير 0807',
        'slug'       => 'nail-dryer-0807',
        'price'      => 19.99,
        'old_price'  => 50,
        'image'      => '/assets/img/demo/nail-dryer-0807.jpg',
        'sku'        => '1033753494',
        'brand'      => null,
        'badges'     => ['flash'], // للبرق
    ],
    [
        'name'       => 'مقص برو ماني رأس عريض (PR-?)',
        'slug'       => 'promani-wide-tip-scissors-pr',
        'price'      => 22.00,
        'old_price'  => 30,
        'image'      => '/assets/img/demo/promani-wide-tip-scissors.jpg',
        'sku'        => '1033755045',
        'brand'      => 'Promani',
        'badges'     => [],
    ],
    [
        'name'       => 'قرَّاطة أظافر برو ماني (PR-102)',
        'slug'       => 'promani-nail-clipper-pr-102',
        'price'      => 25.99,
        'old_price'  => 40,
        'image'      => '/assets/img/demo/promani-nail-clipper-pr-102.jpg',
        'sku'        => '1033755046',
        'brand'      => 'Promani',
        'badges'     => [],
    ],
    [
        'name'       => 'مِبرد أظافر إلكتروني مُفرد',
        'slug'       => 'electric-nail-file-single',
        'price'      => 8.99,
        'old_price'  => 20,
        'image'      => '/assets/img/demo/electric-nail-file.jpg',
        'sku'        => '1033755484',
        'brand'      => null,
        'badges'     => ['flash'], // للبرق
    ],
    [
        'name'       => 'سيباتي الوردي 6 بوصة ميني – مكياج قطعتين',
        'slug'       => 'spatty-pink-6in-mini-2pcs',
        'price'      => 39.99,
        'old_price'  => 50,
        'image'      => '/assets/img/demo/spatty-pink-6in-mini-2pcs.jpg',
        'sku'        => '1033752589',
        'brand'      => 'Spatty',
        'badges'     => [],
        // 'stock'   => 2, // لو بدك تظهر "بقي 2 فقط"
    ],
],
// ===== قسم الجمال > العناية بالأقدام (Foot)
'beauty/herb/foot' => [
    [
        'name'       => 'كريم لمعالجة تشقق وجفاف الكعبين وترطيبهما من كوكو كير',
        'slug'       => 'cococare-heel-crack-repair-cream',
        'price'      => 33.00,
        'old_price'  => 40,
        'image'      => '/assets/img/demo/cococare-heel-crack-repair-cream.jpg',
        'sku'        => '1033752607',
        'brand'      => 'Cococare',
        'badges'     => [], // أضف 'flash' لو بدك أيقونة البرق
    ],
],



// ===== ساعات رجالية - Leather (دفعة واحدة)
'watches/men/leather' => [
    [
        'name'      => 'ساعة رجالي CURREN جلد برتقالي',
        'slug'      => 'curren-men-leather-orange',
        'price'     => 89.99,
        'old_price' => 120,
        'image'     => '/assets/img/demo/watch-curren-leather-orange.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'ساعة رجالي CURREN جلد أزرق',
        'slug'      => 'curren-men-leather-blue',
        'price'     => 89.99,
        'old_price' => 120,
        'image'     => '/assets/img/demo/watch-curren-leather-blue.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'ساعة رجالي CURREN جلد بني/أسود',
        'slug'      => 'curren-men-leather-brown-black',
        'price'     => 89.99,
        'old_price' => 120,
        'image'     => '/assets/img/demo/watch-curren-leather-brown-black.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'ساعة رجالي Mesh CURREN فضي',
        'slug'      => 'curren-men-mesh-silver',
        'price'     => 89.99,
        'old_price' => 120,
        'image'     => '/assets/img/demo/watch-curren-mesh-silver.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'ساعة رجالي CURREN أخضر (Rubber)',
        'slug'      => 'curren-men-green-rubber-1',
        'price'     => 89.99,
        'old_price' => 120,
        'image'     => '/assets/img/demo/watch-curren-green-rubber-1.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'ساعة رجالي CURREN أخضر (Rubber) — موديل 2',
        'slug'      => 'curren-men-green-rubber-2',
        'price'     => 89.99,
        'old_price' => 120,
        'image'     => '/assets/img/demo/watch-curren-green-rubber-2.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'ساعة رجالي CURREN مطاط أسود/أزرق',
        'slug'      => 'curren-men-rubber-black-blue',
        'price'     => 89.99,
        'old_price' => 120,
        'image'     => '/assets/img/demo/watch-curren-rubber-black-blue.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'ساعة ذكية X Ultra سيليكون IP68',
        'slug'      => 'x-ultra-smartwatch-orange',
        'price'     => 269.99,
        'old_price' => 300,
        'image'     => '/assets/img/demo/watch-smart-ultra-orange.jpg',
        'sizes'     => [],
    ],
],








// ===== ساعات رجالية - Metal (دفعة واحدة من الصورة)
'watches/men/metal' => [
    [
        'name'      => 'ساعة رجالي EXTRI إطار ميتال لون أسود وذهبي',
        'slug'      => 'extri-men-metal-black-gold',
        'price'     => 124.99,
        'old_price' => 200,
        'image'     => '/assets/img/demo/metal-extri-black-gold.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'ساعة ذكية رجالي مع كاميرا وشحن HW18 (Metal)',
        'slug'      => 'smartwatch-hw18-metal',
        'price'     => 379.99,
        'old_price' => 400,
        'image'     => '/assets/img/demo/metal-smart-hw18.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'طقم ساعة معدنية للرجال لون فضي SK40',
        'slug'      => 'sk40-men-metal-silver-set',
        'price'     => 269.99,
        'old_price' => 300,
        'image'     => '/assets/img/demo/metal-sk40-silver-set.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'ساعة جيب رومانية معدنية',
        'slug'      => 'pocket-watch-roman-metal',
        'price'     => 34.99,
        'old_price' => 50,
        'image'     => '/assets/img/demo/metal-pocket-roman.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'ساعة رجالي LONGBO إطار ميتال فضي',
        'slug'      => 'longbo-men-metal-silver',
        'price'     => 124.99,
        'old_price' => 200,
        'image'     => '/assets/img/demo/metal-longbo-silver.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'ساعة رجالي 5T إطار ميتال أسود وذهبي',
        'slug'      => '5t-men-metal-black-gold',
        'price'     => 124.99,
        'old_price' => 200,
        'image'     => '/assets/img/demo/metal-5t-black-gold.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'ساعة رجالي 5T إطار ميتال فضي وأسود',
        'slug'      => '5t-men-metal-silver-black',
        'price'     => 124.99,
        'old_price' => 200,
        'image'     => '/assets/img/demo/metal-5t-silver-black.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'ساعة رجالي EXTRI إطار ميتال أسود مع تفاصيل ذهبية',
        'slug'      => 'extri-men-metal-black-gold-accents',
        'price'     => 124.99,
        'old_price' => 200,
        'image'     => '/assets/img/demo/metal-extri-black-gold-accents.jpg',
        'sizes'     => [],
    ],
],


// ===== ساعات نسائية - Leather
'watches/women/leather' => [
    [
        'name'      => 'ساعة جلد للنساء لون زهري',
        'slug'      => 'women-leather-pink',
        'price'     => 19.99,
        'old_price' => 40,
        'image'     => '/assets/img/demo/women-leather-pink.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'ساعة جلد للنساء لون أحمر',
        'slug'      => 'women-leather-red',
        'price'     => 19.99,
        'old_price' => 40,
        'image'     => '/assets/img/demo/women-leather-red.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'ساعة دائرية سيليكون للنساء لون زهري',
        'slug'      => 'women-silicone-round-pink',
        'price'     => 20.99,
        'old_price' => 40,
        'image'     => '/assets/img/demo/women-silicone-round-pink.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'ساعة HW6MAX لون أسود (جلد/Metal)',
        'slug'      => 'women-hw6max-black',
        'price'     => 199.99,
        'old_price' => 260,
        'image'     => '/assets/img/demo/women-hw6max-black.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'ساعة يد سيليكون بعجلة Classic لون أزرق',
        'slug'      => 'women-classic-blue',
        'price'     => 36.99,
        'old_price' => 60,
        'image'     => '/assets/img/demo/women-classic-blue.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'ساعة جلد للنساء لون أبيض',
        'slug'      => 'women-leather-white',
        'price'     => 19.99,
        'old_price' => 40,
        'image'     => '/assets/img/demo/women-leather-white.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'ساعة جلد للنساء لون أخضر',
        'slug'      => 'women-leather-green',
        'price'     => 19.99,
        'old_price' => 40,
        'image'     => '/assets/img/demo/women-leather-green.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'ساعة جلد للنساء لون زهري فاتح',
        'slug'      => 'women-leather-lightpink',
        'price'     => 19.99,
        'old_price' => 40,
        'image'     => '/assets/img/demo/women-leather-lightpink.jpg',
        'sizes'     => [],
    ],
],


// ===== ساعات نسائية - Metal (دفعة من الصورة)
'watches/women/metal' => [
    [
        'name'      => 'ساعة نسائية بسيطة مع حزام معدني لون فضي',
        'slug'      => 'women-metal-simple-silver',
        'price'     => 65.99,
        'old_price' => 120,
        'image'     => '/assets/img/demo/women-metal-simple-silver.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'ساعة نسائية دائرية مع حزام معدني لون ذهبي',
        'slug'      => 'women-metal-round-gold',
        'price'     => 65.99,
        'old_price' => 120,
        'image'     => '/assets/img/demo/women-metal-round-gold.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'ساعة نسائية مستطيلة مع زركون لون فضي',
        'slug'      => 'women-metal-rect-zircon-silver',
        'price'     => 76.99,
        'old_price' => 120,
        'image'     => '/assets/img/demo/women-metal-rect-zircon-silver.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'ساعة نسائية مستطيلة مع زركون لون روز جولد',
        'slug'      => 'women-metal-rect-zircon-rosegold',
        'price'     => 65.99,
        'old_price' => 120,
        'image'     => '/assets/img/demo/women-metal-rect-zircon-rosegold.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'ساعة نسائية مربعة مع حزام معدني لون فضي',
        'slug'      => 'women-metal-square-silver',
        'price'     => 65.99,
        'old_price' => 120,
        'image'     => '/assets/img/demo/women-metal-square-silver.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'ساعة نسائية مربعة مع حزام معدني لون فضي وأخضر',
        'slug'      => 'women-metal-square-silver-green',
        'price'     => 65.99,
        'old_price' => 120,
        'image'     => '/assets/img/demo/women-metal-square-silver-green.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'ساعة نسائية مربعة مع حزام معدني لون ذهبي',
        'slug'      => 'women-metal-square-gold',
        'price'     => 65.99,
        'old_price' => 120,
        'image'     => '/assets/img/demo/women-metal-square-gold.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'ساعة نسائية دائرية لون ذهبي وأسود',
        'slug'      => 'women-metal-round-gold-black',
        'price'     => 84.99,
        'old_price' => 140,
        'image'     => '/assets/img/demo/women-metal-round-gold-black.jpg',
        'sizes'     => [],
    ],
],


// ===== ساعات ذكية - Smart
'watches/smart' => [
    [
        'name'      => 'Apple Watch SE (2022) 2nd Gen مع كفالة سنة',
        'slug'      => 'apple-watch-se-2022-2nd-gen',
        'price'     => 1199.99,
        'old_price' => 1300,
        'image'     => '/assets/img/demo/smart-apple-se-2022.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'ساعة ذكية Z33 لون أسود Smart Watch 7',
        'slug'      => 'z33-smartwatch-black',
        'price'     => 59.99,
        'old_price' => 80,
        'image'     => '/assets/img/demo/smart-z33-black.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'ساعة ذكية سيليكون لون بنفسجي',
        'slug'      => 'smartwatch-purple-silicone',
        'price'     => 199.99,
        'old_price' => 260,
        'image'     => '/assets/img/demo/smart-purple-silicone.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'ساعة ذكية SK42 للرجال مع 3 أحزمة',
        'slug'      => 'smartwatch-sk42-3straps',
        'price'     => 319.99,
        'old_price' => 400,
        'image'     => '/assets/img/demo/smart-sk42-3straps.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'ساعة يد Matrix رجالية دقيقة لرواد الفضاء',
        'slug'      => 'matrix-smartwatch-men',
        'price'     => 67.99,
        'old_price' => 92,
        'image'     => '/assets/img/demo/smart-matrix-men.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'ساعة ذكية رياضية شبابية R3 مع نشاطات مختلفة',
        'slug'      => 'smartwatch-r3-sport',
        'price'     => 219.99,
        'old_price' => 300,
        'image'     => '/assets/img/demo/smart-r3-sport.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'Samsung Galaxy Watch7 مع كفالة سنة',
        'slug'      => 'samsung-galaxy-watch7',
        'price'     => 888.00,
        'old_price' => 1000,
        'image'     => '/assets/img/demo/smart-samsung-galaxy-watch7.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'Apple Watch Series 10 45mm مع كفالة سنة',
        'slug'      => 'apple-watch-series10-45mm',
        'price'     => 1717.00,
        'old_price' => 2000,
        'image'     => '/assets/img/demo/smart-apple-series10-45mm.jpg',
        'sizes'     => [],
    ],
],





// ===== موبايلات - Samsung
'electronics/phones/samsung' => [
    [
        'name'      => 'Samsung Galaxy A16 6GB RAM 128GB مع كفالة سنة',
        'slug'      => 'samsung-galaxy-a16-6-128',
        'price'     => 649.99,
        'old_price' => 750,
        'image'     => '/assets/img/demo/samsung-a16-6-128.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'Samsung Galaxy A16 4GB RAM & 128GB',
        'slug'      => 'samsung-galaxy-a16-4-128',
        'price'     => 619.99,
        'old_price' => 800,
        'image'     => '/assets/img/demo/samsung-a16-4-128.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'Samsung A06 (4/128GB)',
        'slug'      => 'samsung-galaxy-a06-4-128',
        'price'     => 479.99,
        'old_price' => 600,
        'image'     => '/assets/img/demo/samsung-a06-4-128.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'Samsung Galaxy A06 (4/64GB)',
        'slug'      => 'samsung-galaxy-a06-4-64',
        'price'     => 469.99,
        'old_price' => 550,
        'image'     => '/assets/img/demo/samsung-a06-4-64.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'Samsung Galaxy A26 5G (6GB/128GB) مع كفالة سنة',
        'slug'      => 'samsung-galaxy-a26-5g-6-128',
        'price'     => 849.99,
        'old_price' => 900,
        'image'     => '/assets/img/demo/samsung-a26-5g-6-128.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'Samsung Galaxy A16 8GB RAM & 256GB',
        'slug'      => 'samsung-galaxy-a16-8-256',
        'price'     => 819.99,
        'old_price' => 920,
        'image'     => '/assets/img/demo/samsung-a16-8-256.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'Samsung Galaxy A14 6GB RAM 128GB',
        'slug'      => 'samsung-galaxy-a14-6-128',
        'price'     => 799.99,
        'old_price' => 850,
        'image'     => '/assets/img/demo/samsung-a14-6-128.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'Samsung Galaxy A16 6GB RAM 128GB مع كفالة سنة (موديل/صورة أخرى)',
        'slug'      => 'samsung-galaxy-a16-6-128-alt',
        'price'     => 669.99,
        'old_price' => 700,
        'image'     => '/assets/img/demo/samsung-a16-6-128-alt.jpg',
        'sizes'     => [],
    ],
],
// ===== موبايلات - iPhone
'electronics/phones/iphone' => [
    [
        'name'      => 'iPhone 12 Pro Max 512GB كفالة 6 شهور',
        'slug'      => 'iphone-12-pro-max-512',
        'price'     => 2999.99,
        'old_price' => 3100,
        'image'     => '/assets/img/demo/iphone-12-pro-max-512.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'Apple iPhone 16 128GB كفالة سنة',
        'slug'      => 'iphone-16-128',
        'price'     => 2799.99,
        'old_price' => 3400,
        'image'     => '/assets/img/demo/iphone-16-128.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'Apple iPhone 15 256GB كفالة سنة',
        'slug'      => 'iphone-15-256',
        'price'     => 2599.99,
        'old_price' => 3200,
        'image'     => '/assets/img/demo/iphone-15-256.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'Apple iPhone 14 128GB كفالة سنة',
        'slug'      => 'iphone-14-128',
        'price'     => 2279.99,
        'old_price' => 2500,
        'image'     => '/assets/img/demo/iphone-14-128.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'Apple iPhone 15 Pro Max 256GB',
        'slug'      => 'iphone-15-pro-max-256',
        'price'     => 3949.99,
        'old_price' => 4100,
        'image'     => '/assets/img/demo/iphone-15-pro-max-256.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'Apple iPhone 14 Pro Max 512GB (محلية سنة)',
        'slug'      => 'iphone-14-pro-max-512',
        'price'     => 3649.99,
        'old_price' => 3800,
        'image'     => '/assets/img/demo/iphone-14-pro-max-512.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'Apple iPhone 13 Pro Max 256GB (أزرق)',
        'slug'      => 'iphone-13-pro-max-256-blue',
        'price'     => 3199.99,
        'old_price' => 3400,
        'image'     => '/assets/img/demo/iphone-13-pro-max-256-blue.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'Apple iPhone 13 Pro Max 256GB',
        'slug'      => 'iphone-13-pro-max-256',
        'price'     => 2999.99,
        'old_price' => 3200,
        'image'     => '/assets/img/demo/iphone-13-pro-max-256.jpg',
        'sizes'     => [],
    ],
],
// ===== موبايلات - Xiaomi
'electronics/phones/xiaomi' => [
    [
        'name'      => 'Xiaomi Redmi Note 14 Pro 4G 256GB & 8GB RAM كفالة سنة',
        'slug'      => 'xiaomi-redmi-note-14-pro-4g-256-8',
        'price'     => 979.99,
        'old_price' => 1080,
        'image'     => '/assets/img/demo/xiaomi-note14pro-4g-256-8.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'Xiaomi Redmi Note 14 Pro 4G 512GB & 12GB RAM كفالة سنة',
        'slug'      => 'xiaomi-redmi-note-14-pro-4g-512-12',
        'price'     => 1149.99,
        'old_price' => 1250,
        'image'     => '/assets/img/demo/xiaomi-note14pro-4g-512-12.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'Xiaomi Poco C75 256GB & 8GB RAM كفالة سنة',
        'slug'      => 'xiaomi-poco-c75-256-8',
        'price'     => 599.99,
        'old_price' => 700,
        'image'     => '/assets/img/demo/xiaomi-poco-c75-256-8.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'Xiaomi Redmi Note 14 Pro+ 5G كفالة سنة',
        'slug'      => 'xiaomi-redmi-note-14-pro-plus-5g',
        'price'     => 1499.99,
        'old_price' => 1600,
        'image'     => '/assets/img/demo/xiaomi-note14-proplus-5g.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'Xiaomi Poco X7 Pro 512GB & 12GB RAM كفالة سنة',
        'slug'      => 'xiaomi-poco-x7-pro-512-12',
        'price'     => 1349.99,
        'old_price' => 1700,
        'image'     => '/assets/img/demo/xiaomi-poco-x7-pro-512-12.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'Xiaomi Redmi A5 4G 128GB & 4GB RAM كفالة سنة',
        'slug'      => 'xiaomi-redmi-a5-128-4',
        'price'     => 419.99,
        'old_price' => 550,
        'image'     => '/assets/img/demo/xiaomi-redmi-a5-128-4.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'Xiaomi Redmi 13x 256GB & 8GB RAM كفالة سنة',
        'slug'      => 'xiaomi-redmi-13x-256-8',
        'price'     => 649.99,
        'old_price' => 750,
        'image'     => '/assets/img/demo/xiaomi-redmi-13x-256-8.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'Xiaomi Redmi A5 4G 64GB & 3GB RAM كفالة سنة',
        'slug'      => 'xiaomi-redmi-a5-64-3',
        'price'     => 419.99,
        'old_price' => 520,
        'image'     => '/assets/img/demo/xiaomi-redmi-a5-64-3.jpg',
        'sizes'     => [],
    ],
],
// ===== موبايلات - Nokia
'electronics/phones/nokia' => [
    [
        'name'      => 'NOKIA 105 - هاتف نوكيا 105 مع كفالة سنة',
        'slug'      => 'nokia-105-1y-warranty',
        'price'     => 129.99,
        'old_price' => 180,
        'image'     => '/assets/img/demo/nokia-105-1y.jpg',
        'sizes'     => [],
    ],
],
// ===== موبايلات - Oppo
'electronics/phones/oppo' => [
    [
        'name'      => 'Oppo A5 Pro (8GB/256GB) كفالة سنة',
        'slug'      => 'oppo-a5-pro-8-256',
        'price'     => 799.99,
        'old_price' => 900,
        'image'     => '/assets/img/demo/oppo-a5-pro-8-256.jpg',
        'sizes'     => [],
    ],
],
// ===== موبايلات - Realme
'electronics/phones/realme' => [
    [
        'name'      => 'Realme 7 Pro 128GB 8GB كفالة سنة',
        'slug'      => 'realme-7-pro-128-8',
        'price'     => 839.99,
        'old_price' => 900,
        'image'     => '/assets/img/demo/realme-7-pro-128-8.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'Realme C51 128GB & 4GB RAM كفالة سنة',
        'slug'      => 'realme-c51-128-4',
        'price'     => 639.99,
        'old_price' => 700,
        'image'     => '/assets/img/demo/realme-c51-128-4.jpg',
        'sizes'     => [],
    ],
],
// ===== اكسسوارات الكمبيوتر - Pc Accessories
'electronics/computing/pc-accessories' => [
    // Mouse Pads 25x30 cm
    [
        'name'      => 'جلدة ماوس 25×30 سم (تصميم 1)',
        'slug'      => 'mouse-pad-25x30-1',
        'price'     => 3.99,
        'old_price' => 10,
        'image'     => '/assets/img/demo/pc-mousepad-25x30-1.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'جلدة ماوس 25×30 سم (تصميم 2)',
        'slug'      => 'mouse-pad-25x30-2',
        'price'     => 3.99,
        'old_price' => 10,
        'image'     => '/assets/img/demo/pc-mousepad-25x30-2.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'جلدة ماوس 25×30 سم (تصميم 3)',
        'slug'      => 'mouse-pad-25x30-3',
        'price'     => 4.99,
        'old_price' => 10,
        'image'     => '/assets/img/demo/pc-mousepad-25x30-3.jpg',
        'sizes'     => [],
    ],

    // Gamepad
    [
        'name'      => 'يد لعب سلكية للكمبيوتر والهواتف',
        'slug'      => 'wired-gamepad-pc-phone',
        'price'     => 33.00,
        'old_price' => 50,
        'image'     => '/assets/img/demo/pc-wired-gamepad.jpg',
        'sizes'     => [],
    ],

    // Headset
    [
        'name'      => 'سماعة رأس سلكية مع مايكروفون للألعاب موبايل/كمبيوتر',
        'slug'      => 'wired-gaming-headset-mic',
        'price'     => 41.99,
        'old_price' => 80,
        'image'     => '/assets/img/demo/pc-wired-gaming-headset.jpg',
        'sizes'     => [],
    ],

    // Bluetooth LED Speaker
    [
        'name'      => 'سبيكر ميكرو صوت بلوتوث سلكي مع إضاءة LED - بقوة',
        'slug'      => 'bluetooth-led-speaker',
        'price'     => 60.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/pc-bluetooth-led-speaker.jpg',
        'sizes'     => [],
    ],

    // AV to HDMI Converter (1080p)
    [
        'name'      => 'محوّل AV إلى HDMI مع كابل - 1080 بكسل',
        'slug'      => 'av-to-hdmi-1080p',
        'price'     => 41.99,
        'old_price' => 72,
        'image'     => '/assets/img/demo/pc-av-to-hdmi-1080p.jpg',
        'sizes'     => [],
    ],

    // HDMI Cable 2m 8K
    [
        'name'      => 'كابل HDMI طول 2 متر يدعم 8K',
        'slug'      => 'hdmi-cable-2m-8k',
        'price'     => 33.00,
        'old_price' => 60,
        'image'     => '/assets/img/demo/pc-hdmi-2m-8k.jpg',
        'sizes'     => [],
    ],
],
// ===== اكسسوارات الموبايل - Audio
'electronics/mobile-acc/audio' => [
    [
        'name'      => 'ايربود M90 Pro شاشة Type-C (شعار مرسيدس)',
        'slug'      => 'airpods-m90-pro-typec-mercedes',
        'price'     => 33.99,
        'old_price' => 90,
        'image'     => '/assets/img/demo/audio-m90pro-typec.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'soundcore by ANKER K20i سماعات سيمي In-Ear Comfort',
        'slug'      => 'anker-k20i-semi-in-ear',
        'price'     => 159.99,
        'old_price' => 340,
        'image'     => '/assets/img/demo/audio-anker-k20i.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'سماعات سلكية AUX',
        'slug'      => 'wired-aux-earphones',
        'price'     => 8.00,
        'old_price' => 25,
        'image'     => '/assets/img/demo/audio-aux-wired.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'Samsung Original Earphone with volume control',
        'slug'      => 'samsung-original-with-remote',
        'price'     => 12.99,
        'old_price' => 20,
        'image'     => '/assets/img/demo/audio-samsung-with-remote.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'سماعات ايربود للأذن (Over-Ear 421)',
        'slug'      => 'airpods-overear-421',
        'price'     => 111.00,
        'old_price' => 160,
        'image'     => '/assets/img/demo/audio-overear-421.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'بطاريات القوقعة لسماعات الأذن RAYOVAC 675 (باك 60)',
        'slug'      => 'rayovac-675-60pack',
        'price'     => 199.99,
        'old_price' => 220,
        'image'     => '/assets/img/demo/audio-rayovac-675.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'سماعة أذن بلوتوث M165 بمفتاح ذكي (Kebidu)',
        'slug'      => 'bt-headset-m165-kebidu',
        'price'     => 15.99,
        'old_price' => 31,
        'image'     => '/assets/img/demo/audio-m165-kebidu.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'سماعات عظم بلوتوث رياضية مزدوجة',
        'slug'      => 'bone-conduction-sport-dual',
        'price'     => 10.99,
        'old_price' => 38,
        'image'     => '/assets/img/demo/audio-bone-conduction-dual.jpg',
        'sizes'     => [],
    ],
],

// ===== شنط واكسسوارات > اكسسوارات رجال > Belts (مع المسابح مؤقتًا)
'bags/men/belts' => [
    // أحزمة
    [
        'name'      => 'سير (حزام وسط) جلد طبيعي للرجال لون بني مدخوق',
        'slug'      => 'men-belt-brown-punched',
        'price'     => 29.99,
        'old_price' => 40,
        'image'     => '/assets/img/demo/belt-brown-punched.jpg',
        'sizes'     => ['30','32','36','40','44'],
    ],
    [
        'name'      => 'سير (حزام وسط) جلد طبيعي للرجال لون عنابي',
        'slug'      => 'men-belt-maroon',
        'price'     => 29.99,
        'old_price' => 40,
        'image'     => '/assets/img/demo/belt-maroon.jpg',
        'sizes'     => ['28','32','38','40'],
    ],
    [
        'name'      => 'سير (حزام وسط) جلد طبيعي ولادي لون أسود',
        'slug'      => 'boys-belt-black-1',
        'price'     => 14.99,
        'old_price' => 20,
        'image'     => '/assets/img/demo/belt-black-1.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'سير (حزام وسط) جلد طبيعي ولادي لون أسود (تصميم 2)',
        'slug'      => 'boys-belt-black-2',
        'price'     => 14.99,
        'old_price' => 20,
        'image'     => '/assets/img/demo/belt-black-2.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'سير (حزام وسط) رسمي جلد طبيعي للرجال لون أسود',
        'slug'      => 'men-belt-formal-black-1',
        'price'     => 29.99,
        'old_price' => 40,
        'image'     => '/assets/img/demo/belt-formal-black-1.jpg',
        'sizes'     => ['30','32','34'],
    ],
    [
        'name'      => 'سير (حزام وسط) رسمي جلد طبيعي للرجال لون أسود (تصميم 2)',
        'slug'      => 'men-belt-formal-black-2',
        'price'     => 29.99,
        'old_price' => 40,
        'image'     => '/assets/img/demo/belt-formal-black-2.jpg',
        'sizes'     => ['30','32','44'],
    ],
    ],
// ===== شنط واكسسوارات > اكسسوارات رجال > Wallets Men
'bags/men/wallets-men' => [
    [
        'name'      => 'محفظة رجالية جلد لون أسود 9*11 سم',
        'slug'      => 'mens-wallet-black-9x11',
        'price'     => 69.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/wallet-black-9x11.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'محفظة رجالية جلد لون أسود 9*10 سم',
        'slug'      => 'mens-wallet-black-9x10',
        'price'     => 69.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/wallet-black-9x10.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'محفظة رجالية جلد لون بني 9*11 سم',
        'slug'      => 'mens-wallet-brown-9x11',
        'price'     => 69.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/wallet-brown-9x11.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'محفظة رجالية جلد لون بني 9*11 سم (CAMEL)',
        'slug'      => 'mens-wallet-brown-camel-9x11',
        'price'     => 69.99,
        'old_price' => 100,
        'image'     => '/assets/img/demo/wallet-brown-camel-9x11.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'محفظة جلد للرجال لون أسود 10*11 سم بسحاب',
        'slug'      => 'mens-zip-wallet-black-10x11',
        'price'     => 110.99,
        'old_price' => 140,
        'image'     => '/assets/img/demo/wallet-zip-black-10x11.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'محفظة جلد للرجال لون بني 10*11 سم بسحاب',
        'slug'      => 'mens-zip-wallet-brown-10x11',
        'price'     => 110.99,
        'old_price' => 140,
        'image'     => '/assets/img/demo/wallet-zip-brown-10x11.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'محفظة جلد للرجال لون أسود 10*11 سم (شعار غزال) بسحاب',
        'slug'      => 'mens-zip-wallet-black-deer-10x11',
        'price'     => 110.99,
        'old_price' => 140,
        'image'     => '/assets/img/demo/wallet-zip-black-deer-10x11.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'محفظة جلد للرجال لون بني 10*11 سم (زر أمامي) بسحاب',
        'slug'      => 'mens-zip-wallet-brown-button-10x11',
        'price'     => 110.99,
        'old_price' => 140,
        'image'     => '/assets/img/demo/wallet-zip-brown-button-10x11.jpg',
        'sizes'     => [],
    ],
],
// ===== شنط واكسسوارات > اكسسوارات رجال > Sunglasses
'bags/men/sunglasses' => [
    [
        'name'      => 'نظارة شمسية للرجال والنساء - إطار أسود معدني',
        'slug'      => 'unisex-sunglasses-metal-black',
        'price'     => 39.99,
        'old_price' => 54,
        'image'     => '/assets/img/demo/sg-metal-black.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'نظارة شمسية بمشبك لتثبيتها على النظارة الطبية',
        'slug'      => 'clip-on-sunglasses-for-glasses',
        'price'     => 39.99,
        'old_price' => 54,
        'image'     => '/assets/img/demo/sg-clipon.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'نظارة شمسية حساسة للضوء – إطار أسود عدسة سوداء (موديل 209)',
        'slug'      => 'photochromic-aviator-209-black',
        'price'     => 53.99,
        'old_price' => 72,
        'image'     => '/assets/img/demo/sg-aviator-209.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'نظارات ولادي + بيت نظارات MICKEY MOUSE أزرق',
        'slug'      => 'kids-mickey-mouse-sunglasses-blue',
        'price'     => 36.99,
        'old_price' => 50,
        'image'     => '/assets/img/demo/sg-kids-mickey-blue.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'بكيت علبة نظارة مع سحاب – يناسب كل أنواع النظارات',
        'slug'      => 'zip-sunglasses-case-black',
        'price'     => 18.99,
        'old_price' => 40,
        'image'     => '/assets/img/demo/sg-zip-case-black.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'نظارة شمسية للرجال والنساء عدسة مربعة إطار أسود',
        'slug'      => 'unisex-square-black',
        'price'     => 32.99,
        'old_price' => 60,
        'image'     => '/assets/img/demo/sg-square-black.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'نظارة شمسية عصرية حساسة للضوء (رياضية) للرجال والنساء',
        'slug'      => 'sport-photochromic-grey',
        'price'     => 46.99,
        'old_price' => 66,
        'image'     => '/assets/img/demo/sg-sport-grey.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'نظارة رياضية حساسة للضوء للرجال والنساء (عدسة خضراء)',
        'slug'      => 'sport-photochromic-green',
        'price'     => 53.99,
        'old_price' => 72,
        'image'     => '/assets/img/demo/sg-sport-green.jpg',
        'sizes'     => [],
    ],
],
// ===== شنط واكسسوارات > مدرسة وقرطاسية (المنتجات العامة في صفحة القسم)
'bags/school/school' => [
    [
        'name'      => 'حقيبة مدرسية للثانوية لون أسود 37×43 سم',
        'slug'      => 'school-backpack-black-37x43',
        'price'     => 15.99,
        'old_price' => 50,
        'image'     => '/assets/img/demo/school-bag-black-37x43.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'حقيبة مدرسية للثانوية لون كحلي 37×43 سم',
        'slug'      => 'school-backpack-navy-37x43',
        'price'     => 15.99,
        'old_price' => 50,
        'image'     => '/assets/img/demo/school-bag-navy-37x43.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'حقيبة ظهر نايلون بتصميم الكِيسبي لون أحمر',
        'slug'      => 'drawstring-nylon-red',
        'price'     => 9.99,
        'old_price' => 20,
        'image'     => '/assets/img/demo/school-drawstring-red.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'شنطة مدرسية 16 إنش',
        'slug'      => 'school-backpack-16inch-green',
        'price'     => 33.00,
        'old_price' => 50,
        'image'     => '/assets/img/demo/school-bag-16-green.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'حقيبة مدرسية جامعية وللعمل 18 إنش (رمادي) – موديل 1',
        'slug'      => 'college-work-backpack-grey-18-1',
        'price'     => 124.99,
        'old_price' => 200,
        'image'     => '/assets/img/demo/college-work-grey-18-1.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'حقيبة مدرسية جامعية وللعمل 18 إنش (رمادي) – موديل 2',
        'slug'      => 'college-work-backpack-grey-18-2',
        'price'     => 124.99,
        'old_price' => 200,
        'image'     => '/assets/img/demo/college-work-grey-18-2.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'PUBG Tactical Backpack – شنطة وحقـيبة ظهر لعبة ببجي',
        'slug'      => 'pubg-tactical-backpack',
        'price'     => 32.99,
        'old_price' => 60,
        'image'     => '/assets/img/demo/pubg-tactical-backpack.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'حقيبة مدرسية بتصميم دبدوب حجم 16 إنش لون أسود',
        'slug'      => 'school-bag-bear-16-black',
        'price'     => 43.99,
        'old_price' => 50,
        'image'     => '/assets/img/demo/school-bag-bear-16-black.jpg',
        'sizes'     => [],
    ],
],
// ===== شنط واكسسوارات > مدرسة وقرطاسية > Stationery
'bags/school/stationery' => [
    [
        'name'      => 'علبة أقلام جاف مشّك أزرق Pensan موديل 2021 (50 قلم)',
        'slug'      => 'pensan-2021-blue-50',
        'price'     => 19.99,
        'old_price' => 40,
        'image'     => '/assets/img/demo/st-pensan-2021-blue-50.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'ربطة دفاتر مدرسية عربي 40 ورقة (20 دفتر)',
        'slug'      => 'bundle-notebooks-ar-40-20',
        'price'     => 8.99,
        'old_price' => 20,
        'image'     => '/assets/img/demo/st-notebooks-40-20.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'ربطة دفاتر مدرسية عربي 64 ورقة (12 دفتر)',
        'slug'      => 'bundle-notebooks-ar-64-12',
        'price'     => 8.99,
        'old_price' => 20,
        'image'     => '/assets/img/demo/st-notebooks-64-12.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'ربطة دفاتر مدرسية عربي 96 ورقة (8 دفاتر)',
        'slug'      => 'bundle-notebooks-ar-96-8',
        'price'     => 8.99,
        'old_price' => 20,
        'image'     => '/assets/img/demo/st-notebooks-96-8.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'علبة أقلام رصاص 2B عدد 12 مع مبراة (زهري)',
        'slug'      => 'pencils-2b-12-sharpener-pink',
        'price'     => 15.99,
        'old_price' => 25,
        'image'     => '/assets/img/demo/st-pencils-2b-12-pink.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'علبة أقلام رصاص 2B عدد 12 مع مبراة (أزرق)',
        'slug'      => 'pencils-2b-12-sharpener-blue',
        'price'     => 15.99,
        'old_price' => 25,
        'image'     => '/assets/img/demo/st-pencils-2b-12-blue.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'ساعة منبّه لون زهري معدن مع منبّه وإضاءة',
        'slug'      => 'metal-alarm-clock-pink-light',
        'price'     => 19.99,
        'old_price' => 30,
        'image'     => '/assets/img/demo/st-alarm-clock-pink.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'مطرة ماء ديزني مع مصّاصة – تمساح لون أزرق',
        'slug'      => 'disney-bottle-straw-croc-blue',
        'price'     => 10.00,
        'old_price' => 20,
        'image'     => '/assets/img/demo/st-bottle-croc-blue.jpg',
        'sizes'     => [],
    ],
],
// ===== شنط واكسسوارات > اكسسوارات نساء > Shoulder
'bags/women/shoulder' => [
    [
        'name'      => 'شنطة توت ياغ عملية بسيطة بالأقمشة اليدوي ألوان متنوعة (موديل 1)',
        'slug'      => 'tote-handmade-mix-1',
        'price'     => 33.00,
        'old_price' => 40.00,
        'image'     => '/assets/img/demo/shoulder-tote-mix-1.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'شنطة توت ياغ عملية بسيطة بالأقمشة اليدوي ألوان متنوعة (موديل 2)',
        'slug'      => 'tote-handmade-mix-2',
        'price'     => 33.00,
        'old_price' => 40.00,
        'image'     => '/assets/img/demo/shoulder-tote-mix-2.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'شنطة توت ياغ عملية بسيطة بالأقمشة اليدوي ألوان متنوعة (موديل 3)',
        'slug'      => 'tote-handmade-mix-3',
        'price'     => 33.00,
        'old_price' => 40.00,
        'image'     => '/assets/img/demo/shoulder-tote-mix-3.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'محفظة مطرّز ستان بسحّاب (يدوي)',
        'slug'      => 'embroidered-satin-wallet-zip',
        'price'     => 23.99,
        'old_price' => 30.00,
        'image'     => '/assets/img/demo/shoulder-wallet-embroidered-zip.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'شنطة كتف جلد لون عنابي',
        'slug'      => 'shoulder-bag-leather-wine',
        'price'     => 49.99,
        'old_price' => 60.00,
        'image'     => '/assets/img/demo/shoulder-leather-wine.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'شنطة كتف لون بيج',
        'slug'      => 'shoulder-bag-beige',
        'price'     => 49.99,
        'old_price' => 60.00,
        'image'     => '/assets/img/demo/shoulder-bag-beige.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'شنطة توت ياغ عملية بسيطة بالأقمشة اليدوي – نقش أحمر',
        'slug'      => 'tote-handmade-red-pattern',
        'price'     => 33.00,
        'old_price' => 40.00,
        'image'     => '/assets/img/demo/shoulder-tote-red.jpg',
        'sizes'     => [],
    ],
    [
        'name'      => 'شنطة توت ياغ عملية بسيطة بالأقمشة اليدوي – خطوط متعددة',
        'slug'      => 'tote-handmade-multi-stripes',
        'price'     => 33.00,
        'old_price' => 40.00,
        'image'     => '/assets/img/demo/shoulder-tote-stripes.jpg',
        'sizes'     => [],
    ],
],
// ===== شنط واكسسوارات > اكسسوارات نساء > Laptop
'bags/women/laptop' => [
    [
        'name'      => 'حقيبة ظهر متعددة الاستعمال مع مدخل USB لون كاكي',
        'slug'      => 'multiuse-usb-backpack-khaki',
        'price'     => 66.00,
        'old_price' => 80.00,
        'image'     => '/assets/img/demo/laptop-multiuse-usb-khaki.jpg',
    ],
    [
        'name'      => 'شنطة لابتوب لون سكّني',
        'slug'      => 'laptop-bag-grey',
        'price'     => 129.99,
        'old_price' => 180.00,
        'image'     => '/assets/img/demo/laptop-bag-grey.jpg',
    ],
    [
        'name'      => 'شنطة لابتوب لون أسود (موديل قماشي)',
        'slug'      => 'laptop-bag-black-fabric',
        'price'     => 94.99,
        'old_price' => 130.00,
        'image'     => '/assets/img/demo/laptop-bag-black-fabric.jpg',
    ],
    [
        'name'      => 'شنطة لابتوب لون أسود (موديل جلد/هاندل علوي)',
        'slug'      => 'laptop-bag-black-leather',
        'price'     => 129.99,
        'old_price' => 170.00,
        'image'     => '/assets/img/demo/laptop-bag-black-leather.jpg',
    ],
    [
        'name'      => 'حقيبة ظهر لابتوب GBP1 PackBag',
        'slug'      => 'gbp1-laptop-packbag',
        'price'     => 119.99,
        'old_price' => 140.00,
        'image'     => '/assets/img/demo/gbp1-laptop-packbag.jpg',
    ],
    [
        'name'      => 'حقيبة ظهر لأغراض البيبي للحضانات مع سرير للتغيير',
        'slug'      => 'baby-nursery-backpack-changing-bed',
        'price'     => 88.00,
        'old_price' => 120.00,
        'image'     => '/assets/img/demo/baby-nursery-backpack-bed.jpg',
    ],
    [
        'name'      => 'حقيبة ظهر متعددة الاستعمال مع مدخل USB لون بيج',
        'slug'      => 'multiuse-usb-backpack-beige',
        'price'     => 66.00,
        'old_price' => 80.00,
        'image'     => '/assets/img/demo/laptop-multiuse-usb-beige.jpg',
    ],
],
// ===== شنط واكسسوارات > اكسسوارات نساء > Accessories Women
'bags/women/accessories-women' => [
    [
        'name'      => 'سلسال فضي زركون مربع أحمر مع دمعة',
        'slug'      => 'silver-red-zircon-square-necklace',
        'price'     => 8.99,
        'old_price' => 15.00,
        'image'     => '/assets/img/demo/silver-red-zircon-square-necklace.jpg',
    ],
    [
        'name'      => 'سلسال مع زركون مربع (لون: فضي وفوشي)',
        'slug'      => 'silver-zircon-square-pink-necklace',
        'price'     => 8.99,
        'old_price' => 15.00,
        'image'     => '/assets/img/demo/silver-zircon-square-pink-necklace.jpg',
    ],
    [
        'name'      => 'سلسال فان كليف كوكب لون ذهبي وأبيض',
        'slug'      => 'van-cleef-gold-white-necklace',
        'price'     => 8.99,
        'old_price' => 15.00,
        'image'     => '/assets/img/demo/van-cleef-gold-white-necklace.jpg',
    ],
    [
        'name'      => 'سلسال فضي مع زركون مستطيل أبيض',
        'slug'      => 'silver-rectangle-zircon-necklace',
        'price'     => 8.99,
        'old_price' => 15.00,
        'image'     => '/assets/img/demo/silver-rectangle-zircon-necklace.jpg',
    ],
    [
        'name'      => 'سلسال فضي دائري آية قرآنية باللون الأزرق',
        'slug'      => 'silver-blue-circle-necklace',
        'price'     => 8.99,
        'old_price' => 15.00,
        'image'     => '/assets/img/demo/silver-blue-circle-necklace.jpg',
    ],
    [
        'name'      => 'سلسال مع زركون شكل دمعة (لون: فضي وأحمر)',
        'slug'      => 'silver-tear-zircon-red-necklace',
        'price'     => 8.99,
        'old_price' => 15.00,
        'image'     => '/assets/img/demo/silver-tear-zircon-red-necklace.jpg',
    ],
    [
        'name'      => 'سلسال الشمس والقمر لون فضي وذهبي',
        'slug'      => 'sun-moon-silver-gold-necklace',
        'price'     => 8.99,
        'old_price' => 15.00,
        'image'     => '/assets/img/demo/sun-moon-silver-gold-necklace.jpg',
    ],
    [
        'name'      => 'سلسال فضي وأسود على شكل دائري (سورة الفلق)',
        'slug'      => 'silver-black-surah-necklace',
        'price'     => 8.99,
        'old_price' => 15.00,
        'image'     => '/assets/img/demo/silver-black-surah-necklace.jpg',
    ],
],
// ===== الأطفال والألعاب > الألعاب
'kids/games' => [
    [
        'name'      => 'لعبة السلم والحية حجم 24.5×24.5 سم',
        'slug'      => 'snakes-ladders-24-5',
        'price'     => 0.99,
        'old_price' => 5.00,
        'image'     => '/assets/img/demo/games-snakes-ladders-24-5.jpg',
    ],
    [
        'name'      => 'كشاف بطارية يدوي صغير',
        'slug'      => 'mini-hand-flashlight',
        'price'     => 4.99,
        'old_price' => 10.00,
        'image'     => '/assets/img/demo/games-mini-flashlight.jpg',
    ],
    [
        'name'      => 'كشاف بطارية ملون',
        'slug'      => 'color-flashlight',
        'price'     => 4.99,
        'old_price' => 10.00,
        'image'     => '/assets/img/demo/games-color-flashlight.jpg',
    ],
    [
        'name'      => 'لعبة الكرة الطائرة التي تعود لك',
        'slug'      => 'flying-return-ball',
        'price'     => 15.99,
        'old_price' => 20.00,
        'image'     => '/assets/img/demo/games-flying-return-ball.jpg',
    ],
    [
        'name'      => 'لعبة فرد أمريكي مع طلقات',
        'slug'      => 'police-gun-set',
        'price'     => 14.99,
        'old_price' => 25.00,
        'image'     => '/assets/img/demo/games-police-gun-set.jpg',
    ],
    [
        'name'      => 'لوحة خشبية تعليمية للأطفال – أشكال حيوانات',
        'slug'      => 'wooden-animals-puzzle',
        'price'     => 11.99,
        'old_price' => 20.00,
        'image'     => '/assets/img/demo/games-wooden-animals-puzzle.jpg',
    ],
    [
        'name'      => 'طابات ألعاب للأطفال – 32 قطعة',
        'slug'      => 'kids-balls-32pcs',
        'price'     => 22.99,
        'old_price' => 35.00,
        'image'     => '/assets/img/demo/games-balls-32pcs.jpg',
    ],
    [
        'name'      => 'مرجيحة للأطفال ووالد مع حبل لتعليق',
        'slug'      => 'kids-swing-rope',
        'price'     => 144.99,
        'old_price' => 200.00,
        'image'     => '/assets/img/demo/games-kids-swing-rope.jpg',
    ],
    [
        'name'      => 'لعبة أطفال طبل + خرخيشة',
        'slug'      => 'baby-drum-rattle',
        'price'     => 22.99,
        'old_price' => 35.00,
        'image'     => '/assets/img/demo/games-baby-drum-rattle.jpg',
    ],
    [
        'name'      => 'خيمة لعب للأطفال مع 100 طابة',
        'slug'      => 'kids-play-tent-100-balls',
        'price'     => 169.99,
        'old_price' => 240.00,
        'image'     => '/assets/img/demo/games-play-tent-100-balls.jpg',
    ],
    [
        'name'      => 'لعبة تحدي الكريمة الممتعة',
        'slug'      => 'pie-face-challenge',
        'price'     => 84.99,
        'old_price' => 120.00,
        'image'     => '/assets/img/demo/games-pie-face.jpg',
    ],
    [
        'name'      => 'ليجو مغناطيس – برج 97 قطعة',
        'slug'      => 'magnetic-blocks-97pcs',
        'price'     => 144.99,
        'old_price' => 200.00,
        'image'     => '/assets/img/demo/games-magnetic-blocks-97.jpg',
    ],
    [
        'name'      => 'لعبة أطفال سيارة نقل المضخات',
        'slug'      => 'kids-pump-truck',
        'price'     => 22.99,
        'old_price' => 35.00,
        'image'     => '/assets/img/demo/games-kids-pump-truck.jpg',
    ],
],
// ===== الأطفال و الألعاب > الألعاب > Blocks
'kids/games/blocks' => [
    [
        'name'      => 'منشفة استحمام للأطفال رسمة ميكي ماوس',
        'slug'      => 'kids-towel-mickey',
        'price'     => 37.99,
        'old_price' => 60.00,
        'image'     => '/assets/img/demo/kids-towel-mickey.jpg',
    ],
    [
        'name'      => 'كوت محمول للأطفال متعدد الاستخدامات',
        'slug'      => 'portable-baby-cot',
        'price'     => 39.99,
        'old_price' => 50.00,
        'image'     => '/assets/img/demo/portable-baby-cot.jpg',
    ],
    [
        'name'      => 'منشفة استحمام للأطفال مع غطاء للشعر - ميكي (أبيض)',
        'slug'      => 'hooded-towel-mickey-white',
        'price'     => 65.99,
        'old_price' => 90.00,
        'image'     => '/assets/img/demo/hooded-towel-mickey-white.jpg',
    ],
    [
        'name'      => 'منشفة استحمام للأطفال رسمة فروزن',
        'slug'      => 'kids-towel-frozen',
        'price'     => 37.99,
        'old_price' => 60.00,
        'image'     => '/assets/img/demo/kids-towel-frozen.jpg',
    ],
    [
        'name'      => 'هاية فواكه للأطفال من ديزني - لون أحمر',
        'slug'      => 'disney-fruit-feeder-red',
        'price'     => 27.99,
        'old_price' => 40.00,
        'image'     => '/assets/img/demo/fruit-feeder-red.jpg',
    ],
    [
        'name'      => 'هاية فواكه للأطفال من ديزني - لون أزرق',
        'slug'      => 'disney-fruit-feeder-blue',
        'price'     => 27.99,
        'old_price' => 40.00,
        'image'     => '/assets/img/demo/fruit-feeder-blue.jpg',
    ],
    [
        'name'      => 'منشفة استحمام للأطفال رسمة ميني ماوس والأصدقاء',
        'slug'      => 'kids-towel-minnie-friends',
        'price'     => 37.99,
        'old_price' => 60.00,
        'image'     => '/assets/img/demo/kids-towel-minnie-friends.jpg',
    ],
    [
        'name'      => 'منشفة استحمام للأطفال رسمة باتمان',
        'slug'      => 'kids-towel-batman',
        'price'     => 37.99,
        'old_price' => 60.00,
        'image'     => '/assets/img/demo/kids-towel-batman.jpg',
    ],
    [
        'name'      => 'منشفة بغطاء للشعر للأطفال - ميني (وردي)',
        'slug'      => 'hooded-towel-minnie-pink',
        'price'     => 65.99,
        'old_price' => 90.00,
        'image'     => '/assets/img/demo/hooded-towel-minnie-pink.jpg',
    ],
    [
        'name'      => 'منشفة بغطاء للشعر للأطفال - ميني (أبيض)',
        'slug'      => 'hooded-towel-minnie-white',
        'price'     => 65.99,
        'old_price' => 90.00,
        'image'     => '/assets/img/demo/hooded-towel-minnie-white.jpg',
    ],
],

// ==== الرياضة والصحة > الرياضة > Shakers
'sporthealth/sport/shakers' => [
    [
        'name'      => 'مطرة لون ذهبي',
        'slug'      => 'shaker-gold',
        'price'     => 11.00,
        'old_price' => 20.00,
        'image'     => '/assets/img/demo/shaker-gold.jpg',
    ],
    [
        'name'      => 'مطرة لون زهري',
        'slug'      => 'shaker-pink',
        'price'     => 11.00,
        'old_price' => 20.00,
        'image'     => '/assets/img/demo/shaker-pink.jpg',
    ],
    [
        'name'      => 'مطرة ماء ديزني مع مصاصة - تمساح لون أزرق',
        'slug'      => 'disney-straw-bottle-crocodile-blue',
        'price'     => 10.00,
        'old_price' => 20.00,
        'image'     => '/assets/img/demo/disney-straw-bottle-crocodile-blue.jpg',
    ],
    [
        'name'      => 'مطرة لون أبيض',
        'slug'      => 'shaker-white',
        'price'     => 11.00,
        'old_price' => 20.00,
        'image'     => '/assets/img/demo/shaker-white.jpg',
    ],
    [
        'name'      => 'ترموس 550 مل',
        'slug'      => 'thermos-550ml-red',
        'price'     => 44.99,
        'old_price' => 60.00,
        'image'     => '/assets/img/demo/thermos-550ml-red.jpg',
    ],
    [
        'name'      => 'مطرة ماء ستانلس',
        'slug'      => 'stainless-water-bottle-red',
        'price'     => 24.99,
        'old_price' => 39.00,
        'image'     => '/assets/img/demo/stainless-water-bottle-red.jpg',
    ],
    [
        'name'      => 'مطرة لون أزرق',
        'slug'      => 'shaker-light-blue',
        'price'     => 11.00,
        'old_price' => 20.00,
        'image'     => '/assets/img/demo/shaker-light-blue.jpg',
    ],
    [
        'name'      => 'مطرة لون أسود',
        'slug'      => 'shaker-black',
        'price'     => 11.00,
        'old_price' => 20.00,
        'image'     => '/assets/img/demo/shaker-black.jpg',
    ],
],
// ===== الرياضة والصحة > المكملات الغذائية
'sporthealth/sport/supplements' => [
    [
        'name'      => 'BEEF-XP Clear Beef Protein Isolate 1.8kg (Pineapple)',
        'slug'      => 'beef-xp-protein-pineapple',
        'price'     => 279.99,
        'old_price' => 400.00,
        'image'     => '/assets/img/demo/beef-xp-protein-pineapple.jpg',
    ],
    [
        'name'      => 'BEEF-XP Clear Beef Protein Isolate 1.8kg (Orange)',
        'slug'      => 'beef-xp-protein-orange',
        'price'     => 279.99,
        'old_price' => 400.00,
        'image'     => '/assets/img/demo/beef-xp-protein-orange.jpg',
    ],
    [
        'name'      => 'جو سويت محلي للمشروبات الباردة والساخنة 10 مل',
        'slug'      => 'jo-sweet-drink-sweetener',
        'price'     => 19.99,
        'old_price' => 30.00,
        'image'     => '/assets/img/demo/jo-sweet-syrup.jpg',
    ],
    [
        'name'      => 'Nutrex Research Liquid L-Carnitine 3000',
        'slug'      => 'nutrex-liquid-carnitine',
        'price'     => 99.99,
        'old_price' => 150.00,
        'image'     => '/assets/img/demo/nutrex-carnitine.jpg',
    ],
    [
        'name'      => 'Mutant Mass Extreme Gainer',
        'slug'      => 'mutant-mass-extreme',
        'price'     => 294.99,
        'old_price' => 330.00,
        'image'     => '/assets/img/demo/mutant-mass.jpg',
    ],
    [
        'name'      => 'Applied Nutrition Citrulline Malate 2:1 300g',
        'slug'      => 'citrulline-malate-applied',
        'price'     => 108.99,
        'old_price' => 130.00,
        'image'     => '/assets/img/demo/citrulline-malate.jpg',
    ],
    [
        'name'      => 'Evultion Nutrition Creatine 5000 300g',
        'slug'      => 'creatine-5000-evl',
        'price'     => 84.99,
        'old_price' => 120.00,
        'image'     => '/assets/img/demo/creatine-5000.jpg',
    ],
    [
        'name'      => 'BEEF-XP Clear Beef Protein Isolate 1.8kg (Lemon Mint)',
        'slug'      => 'beef-xp-protein-lemon-mint',
        'price'     => 279.99,
        'old_price' => 400.00,
        'image'     => '/assets/img/demo/beef-xp-protein-lemon-mint.jpg',
    ],
],
// ===== الرياضة والصحة > Home Gym
'sporthealth/sport/home-gym' => [
    [
        'name'      => 'أنشِطة لياقة بدنية لتمرين كامل الجسم',
        'slug'      => 'full-body-training-straps',
        'price'     => 179.99,
        'old_price' => 250.00,
        'image'     => '/assets/img/demo/homegym-fullbody-straps.jpg',
    ],
    [
        'name'      => 'حبل سحب لعضلة الثلاثية (Triceps Rope)',
        'slug'      => 'triceps-rope-black',
        'price'     => 83.99,
        'old_price' => 120.00,
        'image'     => '/assets/img/demo/homegym-triceps-rope.jpg',
    ],
    [
        'name'      => 'زوج دمبلز وزن 3 كيلو لون أحمر',
        'slug'      => 'dumbbells-3kg-red',
        'price'     => 67.99,
        'old_price' => 90.00,
        'image'     => '/assets/img/demo/homegym-dumbbells-3kg-red.jpg',
    ],
    [
        'name'      => 'زوج دمبلز وزن 2 كيلو لون أسود وقبضة فضي',
        'slug'      => 'dumbbells-2kg-black-silver',
        'price'     => 41.99,
        'old_price' => 60.00,
        'image'     => '/assets/img/demo/homegym-dumbbells-2kg-black.jpg',
    ],
    [
        'name'      => 'مشّد MWKS Elbow Support (الكوع)',
        'slug'      => 'mwks-elbow-support',
        'price'     => 23.99,
        'old_price' => 50.00,
        'image'     => '/assets/img/demo/homegym-elbow-support.jpg',
    ],
    [
        'name'      => 'مشّد MWKS Knee Support (الركبة)',
        'slug'      => 'mwks-knee-support',
        'price'     => 23.99,
        'old_price' => 50.00,
        'image'     => '/assets/img/demo/homegym-knee-support.jpg',
    ],
    [
        'name'      => 'وزن بيبي دمبل 1 كيلو لون أخضر',
        'slug'      => 'baby-dumbbell-1kg-green',
        'price'     => 24.99,
        'old_price' => 40.00,
        'image'     => '/assets/img/demo/homegym-dumbbell-1kg-green.jpg',
    ],
    [
        'name'      => 'شنطة للجيم لون أسود',
        'slug'      => 'black-gym-bag',
        'price'     => 49.99,
        'old_price' => 70.00,
        'image'     => '/assets/img/demo/homegym-gymbag-black.jpg',
    ],
],
// ===== الرياضة والصحة > Football
'sporthealth/sport/football' => [
    [
        'name'      => 'adidas Kids\' Tiro Match Shin Guards - Black',
        'slug'      => 'adidas-tiro-shin-guards-black',
        'price'     => 89.99,
        'old_price' => 110.00,
        'image'     => '/assets/img/demo/adidas-tiro-shin-guards-black.jpg',
    ],
    [
        'name'      => 'adidas Tiro League Thermally Bonded Ball - Green',
        'slug'      => 'adidas-tiro-league-ball-green',
        'price'     => 139.99,
        'old_price' => 170.00,
        'image'     => '/assets/img/demo/adidas-tiro-league-ball-green.jpg',
    ],
    [
        'name'      => 'حذاء رياضي للأطفال والشباب لون أبيض وأحمر ونعل أسود',
        'slug'      => 'kids-sports-shoes-white-red',
        'price'     => 19.99,
        'old_price' => 40.00,
        'image'     => '/assets/img/demo/kids-sports-shoes-white-red.jpg',
    ],
    [
        'name'      => 'حذاء رياضي للأطفال والشباب لون سكّني وبرتقالي',
        'slug'      => 'kids-sports-shoes-grey-orange',
        'price'     => 19.99,
        'old_price' => 40.00,
        'image'     => '/assets/img/demo/kids-sports-shoes-grey-orange.jpg',
    ],
    [
        'name'      => 'كرة طائرة تدريبية مختومة من الجلد عالي الجودة - V460W',
        'slug'      => 'mikasa-v460w-volleyball',
        'price'     => 167.99,
        'old_price' => 390.00,
        'image'     => '/assets/img/demo/mikasa-v460w-volleyball.jpg',
    ],
    [
        'name'      => 'حذاء رياضي للرجال لون أسود Fila',
        'slug'      => 'fila-men-sports-shoes-black',
        'price'     => 66.00,
        'old_price' => 150.00,
        'image'     => '/assets/img/demo/fila-men-sports-shoes-black.jpg',
    ],
    [
        'name'      => 'حذاء رياضي ولادي لون أسود Fila',
        'slug'      => 'fila-boys-sports-shoes-black',
        'price'     => 66.00,
        'old_price' => 150.00,
        'image'     => '/assets/img/demo/fila-boys-sports-shoes-black.jpg',
    ],
    [
        'name'      => 'adidas Tiro Club Shin Guards - White',
        'slug'      => 'adidas-tiro-shin-guards-white',
        'price'     => 66.00,
        'old_price' => 80.00,
        'image'     => '/assets/img/demo/adidas-tiro-shin-guards-white.jpg',
    ],
],
// ===== الرياضة والصحة > Other
'sporthealth/sport/other' => [
    [
        'name'      => 'Flamingo Premium Sports Wrist Band',
        'slug'      => 'flamingo-wrist-band',
        'price'     => 29.99,
        'old_price' => 45.00,
        'image'     => '/assets/img/demo/flamingo-wrist-band.jpg',
    ],
    [
        'name'      => 'سكيت بورد',
        'slug'      => 'skate-board-basic',
        'price'     => 98.00,
        'old_price' => 110.00,
        'image'     => '/assets/img/demo/skate-board-basic.jpg',
    ],
    [
        'name'      => 'بشكير لفحة الرياضيين قياس 15*110 سم',
        'slug'      => 'sports-scarf-15x110',
        'price'     => 2.99,
        'old_price' => 10.00,
        'image'     => '/assets/img/demo/sports-scarf-15x110.jpg',
    ],
    [
        'name'      => 'رؤوس عصا البلياردو 9مم عدد 100 Jinli Ganzui',
        'slug'      => 'billiard-cue-tips-9mm-100pcs',
        'price'     => 38.99,
        'old_price' => 80.00,
        'image'     => '/assets/img/demo/billiard-cue-tips-9mm-100pcs.jpg',
    ],
    [
        'name'      => 'STIGA كرات تنس (3 عدد)',
        'slug'      => 'stiga-table-tennis-3pcs',
        'price'     => 5.99,
        'old_price' => 16.00,
        'image'     => '/assets/img/demo/stiga-table-tennis-3pcs.jpg',
    ],
    [
        'name'      => 'كف بلياردو أسود/أزرق',
        'slug'      => 'billiard-glove-black-blue',
        'price'     => 9.99,
        'old_price' => 22.00,
        'image'     => '/assets/img/demo/billiard-glove-black-blue.jpg',
    ],
    [
        'name'      => 'Flamingo Sports Head Band',
        'slug'      => 'flamingo-head-band',
        'price'     => 34.99,
        'old_price' => 60.00,
        'image'     => '/assets/img/demo/flamingo-head-band.jpg',
    ],
],
// ===== الرياضة والصحة > الصحة > Braces
'sporthealth/health/braces' => [
    [
        'name'      => 'مشّد تدفئة كهربائي للركبة من خلال سلك USB',
        'slug'      => 'usb-electric-knee-brace',
        'price'     => 67.99,
        'old_price' => 120.00,
        'image'     => '/assets/img/demo/usb-electric-knee-brace.jpg',
    ],
    [
        'name'      => 'مشّد ركبة مع أشرطة قابلة للتعديل - مقاس Small',
        'slug'      => 'adjustable-knee-brace-small',
        'price'     => 53.99,
        'old_price' => 90.00,
        'image'     => '/assets/img/demo/adjustable-knee-brace-small.jpg',
    ],
    [
        'name'      => 'مشّد تدفئة كهربائي للكتف لتخفيف الألم',
        'slug'      => 'usb-electric-shoulder-brace',
        'price'     => 95.99,
        'old_price' => 150.00,
        'image'     => '/assets/img/demo/usb-electric-shoulder-brace.jpg',
    ],
    [
        'name'      => 'مشّد دعامة جبيرة معصم قابلة للتعديل (رجال/نساء)',
        'slug'      => 'adjustable-wrist-brace',
        'price'     => 53.99,
        'old_price' => 72.00,
        'image'     => '/assets/img/demo/adjustable-wrist-brace.jpg',
    ],
    [
        'name'      => 'دعامة مشّد لمنطقة الإبهام لتخفيف الألم',
        'slug'      => 'thumb-support-brace',
        'price'     => 32.99,
        'old_price' => 60.00,
        'image'     => '/assets/img/demo/thumb-support-brace.jpg',
    ],
    [
        'name'      => 'دعامة الرقبة الطبية لتخفيف الألم',
        'slug'      => 'neck-support-brace',
        'price'     => 39.99,
        'old_price' => 70.00,
        'image'     => '/assets/img/demo/neck-support-brace.jpg',
    ],
    [
        'name'      => 'دعامة مشّد الركبة قابلة للتعديل مع حلقة GBA',
        'slug'      => 'gba-knee-support-brace',
        'price'     => 53.99,
        'old_price' => 90.00,
        'image'     => '/assets/img/demo/gba-knee-support-brace.jpg',
    ],
    [
        'name'      => 'دعامة للركبة قابلة للتعديل بألوان متعددة',
        'slug'      => 'colorful-knee-brace',
        'price'     => 25.99,
        'old_price' => 50.00,
        'image'     => '/assets/img/demo/colorful-knee-brace.jpg',
    ],
],
// ===== الرياضة و الصحة > الصحة > Massage
'sporthealth/health/massage' => [
    [
        'name'      => 'جهاز تدفئة القدمين في فصل الشتاء - خفيف وناعم ومريح',
        'slug'      => 'winter-foot-warmer-pad',
        'price'     => 109.99,
        'old_price' => 180.00,
        'image'     => '/assets/img/demo/massage-foot-warmer.jpg',
    ],
    [
        'name'      => 'أداة تدليك الرقبة اليدوي ذات ست كرات دوّارة (لون زهري)',
        'slug'      => 'manual-neck-massager-6-balls-pink',
        'price'     => 55.99,
        'old_price' => 120.00,
        'image'     => '/assets/img/demo/manual-neck-massager-6-balls-pink.jpg',
    ],
    [
        'name'      => 'مسدس تدليك صغير Mini Fascial Gun',
        'slug'      => 'mini-fascial-massage-gun',
        'price'     => 29.99,
        'old_price' => 40.00,
        'image'     => '/assets/img/demo/mini-fascial-massage-gun.jpg',
    ],
    [
        'name'      => 'جهاز تدليك عضلي Percussion من FineLife',
        'slug'      => 'finelife-percussion-massager',
        'price'     => 59.99,
        'old_price' => 100.00,
        'image'     => '/assets/img/demo/finelife-percussion-massager.jpg',
    ],
    [
        'name'      => 'مقعد التدليك الكهربائي للمنزل والسيارة مع جهاز تحكّم عن بُعد',
        'slug'      => 'electric-massage-seat-remote',
        'price'     => 107.99,
        'old_price' => 180.00,
        'image'     => '/assets/img/demo/electric-massage-seat-remote.jpg',
    ],
    [
        'name'      => 'أداة تدليك الظهر الدوّارة (لون أزرق)',
        'slug'      => 'back-roller-massager-blue',
        'price'     => 32.99,
        'old_price' => 50.00,
        'image'     => '/assets/img/demo/back-roller-massager-blue.jpg',
    ],
    [
        'name'      => 'قلم الوخز بالإبر الإلكتروني لتخفيف الألم – مثالي للنقاط',
        'slug'      => 'electronic-acupuncture-pen',
        'price'     => 46.99,
        'old_price' => 80.00,
        'image'     => '/assets/img/demo/electronic-acupuncture-pen.jpg',
    ],
    [
        'name'      => 'كرة دوّارة يدوية طبية آمنة للتدليك والمساج للمناطق المختلفة',
        'slug'      => 'hand-roller-ball-massager',
        'price'     => 60.99,
        'old_price' => 100.00,
        'image'     => '/assets/img/demo/hand-roller-ball-massager.jpg',
    ],
],
// ===== الرياضة و الصحة > Personal
'sporthealth/health/personal' => [
    [
        'name'      => 'يوكو كريم الوجه المنعش المضاد للشيخوخة 25 غم',
        'slug'      => 'yoko-anti-aging-cream-25g',
        'price'     => 49.99,
        'old_price' => 65.00,
        'image'     => '/assets/img/demo/yoko-anti-aging-cream-25g.jpg',
    ],
    [
        'name'      => 'ماكينة إزالة شعر الوجه',
        'slug'      => 'face-hair-remover',
        'price'     => 22.00,
        'old_price' => 25.00,
        'image'     => '/assets/img/demo/face-hair-remover.jpg',
    ],
    [
        'name'      => 'واقي ذكري مع نتوءات مصنوع من اللاتكس الطبيعي',
        'slug'      => 'natural-latex-condom',
        'price'     => 24.99,
        'old_price' => 30.00,
        'image'     => '/assets/img/demo/natural-latex-condom.jpg',
    ],
    [
        'name'      => 'ليف موس للتسمير الذاتي',
        'slug'      => 'self-tan-mousse',
        'price'     => 37.99,
        'old_price' => 70.00,
        'image'     => '/assets/img/demo/self-tan-mousse.jpg',
    ],
    [
        'name'      => 'White Glo Deep Stain Remover 150g - فرشاة أسنان',
        'slug'      => 'white-glo-deep-stain',
        'price'     => 33.00,
        'old_price' => 50.00,
        'image'     => '/assets/img/demo/white-glo-deep-stain.jpg',
    ],
    [
        'name'      => 'NUDE FUSION كبسولة لرائحة الفم بنكهة النعناع 30 كبسولة',
        'slug'      => 'nude-fusion-mint-capsules',
        'price'     => 22.00,
        'old_price' => 30.00,
        'image'     => '/assets/img/demo/nude-fusion-mint.jpg',
    ],
    [
        'name'      => 'يوكو غسول وجه بالصبار لنعومة الوجه والنضارة 150 مل',
        'slug'      => 'yoko-aloe-vera-face-wash-150ml',
        'price'     => 33.00,
        'old_price' => 49.00,
        'image'     => '/assets/img/demo/yoko-aloe-face-wash.jpg',
    ],
    [
        'name'      => 'يوكو غسول الوجه المنعش المضاد للشيخوخة 100 مل',
        'slug'      => 'yoko-anti-aging-face-wash-100ml',
        'price'     => 44.00,
        'old_price' => 60.00,
        'image'     => '/assets/img/demo/yoko-anti-aging-face-wash.jpg',
    ],
],
// ===== الرياضة والصحة > الصحة > Pillows
'sporthealth/health/pillows' => [
    [
        'name'      => 'Flamingo Memmory Foam Back Rest (With Stand)',
        'slug'      => 'flamingo-backrest-stand',
        'price'     => 169.99,
        'old_price' => 190.00,
        'image'     => '/assets/img/demo/flamingo-backrest-stand.jpg',
    ],
    [
        'name'      => 'Flamingo Premium Memory Foam Pillow',
        'slug'      => 'flamingo-premium-memory-foam-pillow',
        'price'     => 259.99,
        'old_price' => 300.00,
        'image'     => '/assets/img/demo/flamingo-premium-memory-pillow.jpg',
    ],
    [
        'name'      => 'مساج رقبة',
        'slug'      => 'neck-massage-pillow',
        'price'     => 34.99,
        'old_price' => 100.00,
        'image'     => '/assets/img/demo/neck-massage-pillow.jpg',
    ],
    [
        'name'      => 'وسادة نفخ متعددة الاستعمال لون أسود',
        'slug'      => 'black-inflatable-pillow',
        'price'     => 32.99,
        'old_price' => 40.00,
        'image'     => '/assets/img/demo/black-inflatable-pillow.jpg',
    ],
    [
        'name'      => 'Flamingo Flamingo Back Rest Maroon',
        'slug'      => 'flamingo-backrest-maroon',
        'price'     => 169.99,
        'old_price' => 200.00,
        'image'     => '/assets/img/demo/flamingo-backrest-maroon.jpg',
    ],
    [
        'name'      => 'Flamingo Contoured Pillow Universal',
        'slug'      => 'flamingo-contoured-pillow',
        'price'     => 289.99,
        'old_price' => 350.00,
        'image'     => '/assets/img/demo/flamingo-contoured-pillow.jpg',
    ],
    [
        'name'      => 'Flamingo Coccyx Cushion-Soft',
        'slug'      => 'flamingo-coccyx-cushion',
        'price'     => 259.99,
        'old_price' => 300.00,
        'image'     => '/assets/img/demo/flamingo-coccyx-cushion.jpg',
    ],
    [
        'name'      => 'Flamingo Memmory Foam Back Rest (Without Stand)',
        'slug'      => 'flamingo-backrest-without-stand',
        'price'     => 179.99,
        'old_price' => 200.00,
        'image'     => '/assets/img/demo/flamingo-backrest-without-stand.jpg',
    ],
],
// ===== الرياضة والصحة > الصحة > Insoles
'sporthealth/health/insoles' => [
    [
        'name'      => 'جربات سيليكون صندل',
        'slug'      => 'silicon-sandal-socks',
        'price'     => 6.99,
        'old_price' => 15.00,
        'image'     => '/assets/img/demo/silicon-sandal-socks.jpg',
    ],
    [
        'name'      => 'نعل وضبان طبي داخلي للأحذية (41-46) لون أزرق',
        'slug'      => 'medical-insole-blue-41-46',
        'price'     => 14.99,
        'old_price' => 30.00,
        'image'     => '/assets/img/demo/medical-insole-blue.jpg',
    ],
    [
        'name'      => 'ضبان طبي قابل للتعديل لكل الأحذية - أسود',
        'slug'      => 'adjustable-insole-black',
        'price'     => 14.99,
        'old_price' => 30.00,
        'image'     => '/assets/img/demo/adjustable-insole-black.jpg',
    ],
    [
        'name'      => 'طقم ضبان سيليكون ستاتي 0396',
        'slug'      => 'silicon-insole-0396',
        'price'     => 6.99,
        'old_price' => 15.00,
        'image'     => '/assets/img/demo/silicon-insole-0396.jpg',
    ],
    [
        'name'      => 'Flamingo Premium Silicon Heel Care Pad-Pair',
        'slug'      => 'flamingo-silicon-heel-pad',
        'price'     => 99.99,
        'old_price' => 130.00,
        'image'     => '/assets/img/demo/flamingo-silicon-heel-pad.jpg',
    ],
    [
        'name'      => 'ضبان طبي للأطفال من ICEMEN (33-35)',
        'slug'      => 'kids-icemen-insole-33-35',
        'price'     => 41.99,
        'old_price' => 50.00,
        'image'     => '/assets/img/demo/kids-icemen-insole.jpg',
    ],
    [
        'name'      => 'قطعة ضبان جلد طبية لمشط القدم وأوجاع الوقوف الطويل',
        'slug'      => 'leather-foot-insole',
        'price'     => 33.99,
        'old_price' => 40.00,
        'image'     => '/assets/img/demo/leather-foot-insole.jpg',
    ],
    [
        'name'      => 'نعل أحذية إيفا ميموري فوم - تصميم متين يمنع الالتهاب',
        'slug'      => 'eva-memory-foam-insole',
        'price'     => 7.99,
        'old_price' => 30.00,
        'image'     => '/assets/img/demo/eva-memory-foam-insole.jpg',
    ],
],
// ===== الرياضة والصحة > الصحة > Supplies
'sporthealth/health/supplies' => [
    [
        'name'      => 'كريم الشفاء المطهر للجروح من ليزر وايت',
        'slug'      => 'laserwhite-antiseptic-wound-cream',
        'price'     => 20.99,
        'old_price' => 40.00,
        'image'     => '/assets/img/demo/supplies-laserwhite-cream.jpg',
    ],
    [
        'name'      => 'أكياس بخاخ لترطيب الجيوب الأنفية بخ 5 أكياس',
        'slug'      => 'neilmed-sinus-rinse-5-packets',
        'price'     => 25.99,
        'old_price' => 50.00,
        'image'     => '/assets/img/demo/supplies-sinus-rinse-5.jpg',
    ],
    [
        'name'      => 'غسول لترطيب الجيوب الأنفية 15 كيس مع بخاخ 30 مل',
        'slug'      => 'sinus-rinse-15-packets-with-spray-30ml',
        'price'     => 39.99,
        'old_price' => 70.00,
        'image'     => '/assets/img/demo/supplies-sinus-rinse-15-spray.jpg',
    ],
    [
        'name'      => 'مزيل شمع الأذن اللاسلكي بمنظار الأذن مع إضاءة LED',
        'slug'      => 'smart-visual-ear-cleaner-led',
        'price'     => 67.99,
        'old_price' => 120.00,
        'image'     => '/assets/img/demo/supplies-ear-cleaner-led.jpg',
    ],
    [
        'name'      => 'مشد طبي رياضي 4.5m*75mm لون أسود',
        'slug'      => 'sport-elastic-bandage-75mm-black',
        'price'     => 35.99,
        'old_price' => 50.00,
        'image'     => '/assets/img/demo/supplies-elastic-bandage-black.jpg',
    ],
    [
        'name'      => 'مشد طبي رياضي 4.5m*75mm لون أزرق',
        'slug'      => 'sport-elastic-bandage-75mm-blue',
        'price'     => 35.99,
        'old_price' => 50.00,
        'image'     => '/assets/img/demo/supplies-elastic-bandage-blue.jpg',
    ],
    [
        'name'      => 'ميزان حرارة رقمي',
        'slug'      => 'digital-thermometer-pen',
        'price'     => 9.99,
        'old_price' => 16.00,
        'image'     => '/assets/img/demo/supplies-digital-thermometer.jpg',
    ],
    [
        'name'      => 'ميزان حرارة محمول ودقيق جدًا مع شاشة LCD ملونة',
        'slug'      => 'portable-lcd-thermometer',
        'price'     => 49.99,
        'old_price' => 90.00,
        'image'     => '/assets/img/demo/supplies-lcd-thermometer.jpg',
    ],
],
// ===== الكتب و الروايات > Education
'books/education' => [
    [
        'name'      => 'كتاب علم طفلك الأرقام',
        'slug'      => 'kids-learn-numbers',
        'price'     => 15.99,
        'old_price' => 20.00,
        'image'     => '/assets/img/demo/kids-learn-numbers.jpg',
    ],
    [
        'name'      => 'Teach Kids English Pupil’s Book كتاب إنجليزي تعليمي',
        'slug'      => 'teach-kids-english-pupil',
        'price'     => 15.99,
        'old_price' => 20.00,
        'image'     => '/assets/img/demo/teach-kids-english-pupil.jpg',
    ],
    [
        'name'      => 'كتاب تعلم اللغة الإنجليزية للأطفال Learn English Now',
        'slug'      => 'kids-learn-english-now',
        'price'     => 15.99,
        'old_price' => 20.00,
        'image'     => '/assets/img/demo/kids-learn-english-now.jpg',
    ],
    [
        'name'      => 'Teach Kids English Activity Book كتاب إنجليزي تعليمي',
        'slug'      => 'teach-kids-english-activity',
        'price'     => 15.99,
        'old_price' => 20.00,
        'image'     => '/assets/img/demo/teach-kids-english-activity.jpg',
    ],
    [
        'name'      => 'كتاب أحب الرياضيات إعداد يوسف عبيد الله',
        'slug'      => 'kids-math-book',
        'price'     => 15.99,
        'old_price' => 20.00,
        'image'     => '/assets/img/demo/kids-math-book.jpg',
    ],
    [
        'name'      => 'كتاب تعليمي للأطفال من الألف إلى الياء',
        'slug'      => 'kids-alphabet-book',
        'price'     => 12.99,
        'old_price' => 20.00,
        'image'     => '/assets/img/demo/kids-alphabet-book.jpg',
    ],
    [
        'name'      => 'كتاب لتعليم كتابة الحروف الإنجليزية Super Handwriting',
        'slug'      => 'kids-super-handwriting',
        'price'     => 15.99,
        'old_price' => 20.00,
        'image'     => '/assets/img/demo/kids-super-handwriting.jpg',
    ],
    [
        'name'      => 'كتاب اختبار ذكاء طفلك لعمر 4-7 سنوات إعداد يوسف عبيد الله',
        'slug'      => 'kids-intelligence-test',
        'price'     => 15.99,
        'old_price' => 20.00,
        'image'     => '/assets/img/demo/kids-intelligence-test.jpg',
    ],
],
// ===== كتب وروايات - روايات
'books/novels' => [
    [
        'name'      => 'رواية أنت كل أشيائي الجميلة',
        'slug'      => 'novel-all-my-beautiful-things',
        'price'     => 15.99,
        'old_price' => 55.00,
        'image'     => '/assets/img/demo/novel-all-my-beautiful-things.jpg',
    ],
    [
        'name'      => 'صباح الخير أيها الوحش',
        'slug'      => 'novel-good-morning-monster',
        'price'     => 15.99,
        'old_price' => 60.00,
        'image'     => '/assets/img/demo/novel-good-morning-monster.jpg',
    ],
    [
        'name'      => 'لأنك الله (معراج النفوس المطمئنة)',
        'slug'      => 'novel-li-annaka-allah',
        'price'     => 14.99,
        'old_price' => 20.00,
        'image'     => '/assets/img/demo/novel-li-annaka-allah.jpg',
    ],
    [
        'name'      => 'قصة مجسمة للأطفال توتة والديناصورات',
        'slug'      => 'kids-story-tota-dinosaurs',
        'price'     => 12.99,
        'old_price' => 20.00,
        'image'     => '/assets/img/demo/kids-story-tota-dinosaurs.jpg',
    ],
    [
        'name'      => 'رواية قضية ست الحسن',
        'slug'      => 'novel-case-of-sit-al-hosn',
        'price'     => 15.99,
        'old_price' => 60.00,
        'image'     => '/assets/img/demo/novel-case-of-sit-al-hosn.jpg',
    ],
    [
        'name'      => 'قوارير - د. أحمد الديب',
        'slug'      => 'novel-quwarir',
        'price'     => 15.99,
        'old_price' => 40.00,
        'image'     => '/assets/img/demo/novel-quwarir.jpg',
    ],
    [
        'name'      => 'رواية بغض - أدهم شرقاوي',
        'slug'      => 'novel-boghd',
        'price'     => 22.00,
        'old_price' => 30.00,
        'image'     => '/assets/img/demo/novel-boghd.jpg',
    ],
    [
        'name'      => 'يسمعون حسيسها - أيمن العتوم',
        'slug'      => 'novel-they-hear-her-whisper',
        'price'     => 15.99,
        'old_price' => 40.00,
        'image'     => '/assets/img/demo/novel-they-hear-her-whisper.jpg',
    ],
],

        ];

        if ($path === '__catalogue_by_path__') {
            return $all;
        }

        if ($path === '*') {
            return array_values(array_merge(...array_values($all)));
        }

        return $all[$path] ?? [];
    }
}




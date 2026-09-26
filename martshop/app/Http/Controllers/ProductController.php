<?php

namespace App\Http\Controllers;
use App\Http\Controllers\CategoryController;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Services\CatalogQuery;
use App\Services\MarketplaceSettings;
use Illuminate\Support\Arr;

class ProductController extends Controller
{
    public function show(string $slug, CatalogQuery $catalogue, MarketplaceSettings $settings)
    {
        $deliveryText = $settings->deliveryText();
        
        // (1) من قاعدة البيانات أولاً
        $product = Product::query()
            ->select(['id', 'brand_id', 'name', 'slug', 'price', 'sale_price', 'image', 'source'])
            ->with([
                'brand:id,name',
                'variants' => fn ($query) => $query->available()
                    ->select(['id', 'product_id', 'size_type', 'size_value', 'color']),
            ])
            ->where('slug', $slug)
            ->publishedForStore()
            ->first();

        if ($product) {
            $offer = $catalogue->bestOffer($product);
            if (! $offer && $product->source === 'merchant_submission') {
                abort(404);
            }
            $priceNow = $offer ? (float) $offer->price : (float) $product->final_price;
            $compareAtPrice = $offer?->compare_at_price !== null
                ? (float) $offer->compare_at_price
                : ($product->sale_price !== null && (float) $product->price > $priceNow
                    ? (float) $product->price
                    : null);
            $hasSale = $compareAtPrice !== null && $compareAtPrice > $priceNow;
            $imageUrl = $product->image ? asset($product->image) : asset('assets/img/placeholder.png');
            $offerVariants = $offer?->variants ?? collect();

            if ($offerVariants->isNotEmpty()) {
                $sizes = $offerVariants
                    ->map(fn ($variant) => $variant->attributes['size'] ?? null)
                    ->filter()
                    ->unique()
                    ->values();
                $alphaSizes = $sizes->reject(fn ($size) => is_numeric($size))->values();
                $numSizes = $sizes->filter(fn ($size) => is_numeric($size))->values();
                $colors = $offerVariants
                    ->map(fn ($variant) => $variant->attributes['color'] ?? null)
                    ->filter()
                    ->unique()
                    ->values();
            } else {
                $alphaSizes = $product->variants
                    ->where('size_type','alpha')->pluck('size_value')->filter()->unique()->values();
                $numSizes = $product->variants
                    ->where('size_type','num')->pluck('size_value')->filter()->unique()->values();
                $colors = $product->variants
                    ->pluck('color')->filter()->unique()->values();
            }

            return view('product.show', compact(
                'product', 'offer', 'hasSale', 'priceNow', 'compareAtPrice', 'imageUrl',
                'alphaSizes', 'numSizes', 'colors', 'offerVariants', 'deliveryText'
            ));
        }

        // Never expose pending/rejected merchant submissions through the legacy slug fallback.
        if (Product::query()->where('slug', $slug)->exists()) {
            abort(404);
        }
        

        if (! config('catalog.legacy_fallback_enabled', false)) {
            abort(404);
        }

        // (2) من الديمو لو مش موجود بقاعدة البيانات
        $demo = $this->demoBySlug($slug);
        if ($demo) {
            return $this->renderFromArray($demo, $deliveryText);
        }

        abort(404);
        
        
    }

    /* =================== Render helper =================== */
    private function renderFromArray(array $data, string $deliveryText)
    {
        $brandName = $this->inferBrand($data['name'] ?? '') ?? 'Generic';
        $colorName = $this->inferColor($data['name'] ?? '');

        $fake = new \stdClass();
        $fake->id    = 0;
        $fake->sku   = null;
        $fake->name  = $data['name'] ?? 'منتج';
        $fake->slug  = $data['slug'];
        $fake->price = (float)($data['price'] ?? 0);
        $fake->sale_price = null; // للديمو فقط
        $fake->brand = (object)['name' => $brandName];
        $fake->image = $data['image'] ?? null;
        $fake->status = 'active';

        $sizes = collect(Arr::wrap($data['sizes'] ?? []));
        $fake->variants = $sizes->map(function($s) use ($colorName){
            $numeric = is_numeric(str_replace('.','',$s));
            return (object)[
                'size_type'  => $numeric ? 'num' : 'alpha',
                'size_value' => $numeric ? (float)$s : (string)$s,
                'color'      => $colorName,
            ];
        });

        $hasSale  = isset($data['old_price']) && $data['old_price'] > $data['price'];
        $priceNow = (float)($data['price'] ?? 0);
        $compareAtPrice = $hasSale ? (float) $data['old_price'] : null;
        $imageUrl = $fake->image
            ? (str_starts_with($fake->image, 'http') || str_starts_with($fake->image, '/')
                ? $fake->image : asset($fake->image))
            : asset('assets/img/placeholder.png');

        $alphaSizes = $fake->variants
            ->filter(fn($v)=>$v->size_type==='alpha')->pluck('size_value')->filter()->unique()->values();
        $numSizes = $fake->variants
            ->filter(fn($v)=>$v->size_type==='num')->pluck('size_value')->filter()->unique()->values();
        $colors = collect($colorName ? [$colorName] : []);

        return view('product.show', [
            'product'    => $fake,
            'hasSale'    => $hasSale,
            'priceNow'   => $priceNow,
            'compareAtPrice' => $compareAtPrice,
            'offer'      => null,
            'imageUrl'   => $imageUrl,
            'alphaSizes' => $alphaSizes,
            'numSizes'   => $numSizes,
            'colors'     => $colors,
            'offerVariants' => collect(),
            'deliveryText' => $deliveryText,
        ]);
    }

    /* =================== Demo data =================== */
   
private function demoBySlug(string $slug): ?array
{
    // كل المسارات اللي فيها داتا داخل CategoryController::demoProducts($path)
    $paths = [
        // ===== أحذية
        // رجالي
        'shoes/men/sport','shoes/men/formal','shoes/men/boots','shoes/men/casual',
        'shoes/men/topsider','shoes/men/medical','shoes/men/sandals',

        // نسائي
        'shoes/women/sport','shoes/women/boots','shoes/women/heels','shoes/women/medical',
        'shoes/women/bridal','shoes/women/casual','shoes/women/sandals',

        // أطفال (لو عندك تفريعات أضفها)
        'shoes/kids',

        // ===== ملابس
        // رجالي
        'clothing/men/jeans','clothing/men/pants','clothing/men/sport','clothing/men/shorts',
        'clothing/men/shirts','clothing/men/tshirts','clothing/men/pajamas',
        'clothing/men/jackets','clothing/men/underwear',

        // نسائي
        'clothing/women/underwear','clothing/women/homewear','clothing/women/pajamas',
        'clothing/women/jackets','clothing/women/tops','clothing/women/abayas',
        'clothing/women/dresses',

        // أطفال
        'clothing/kids/sets','clothing/kids/tops','clothing/kids/dresses',
        'clothing/kids/jackets','clothing/kids/bottoms','clothing/kids/underwear',
        'clothing/kids/blankets',
     // ===== عطور
        'perfumes/men','perfumes/women','perfumes/air','perfumes/bukhoor','perfumes/body','perfumes/hair',

        // ===== الجمال (Makeup) و MART HERB (العناية)
        'beauty/makeup/lips','beauty/makeup/eyes','beauty/makeup/face','beauty/makeup/nails',
        'beauty/herb/skin','beauty/herb/body','beauty/herb/hair',
        'beauty/herb/mouth_teeth','beauty/herb/nailcare','beauty/herb/foot','beauty/herb/oral',
   // --- أحذية ---
        'shoes/men/sport','shoes/men/formal','shoes/men/boots','shoes/men/casual',
        'shoes/men/topsider','shoes/men/medical','shoes/men/sandals',
        'shoes/women/sport','shoes/women/boots','shoes/women/heels','shoes/women/medical',
        'shoes/women/bridal','shoes/women/casual','shoes/women/sandals',
        'shoes/kids',

        // --- ملابس ---
        'clothing/men/jeans','clothing/men/pants','clothing/men/sport','clothing/men/shorts',
        'clothing/men/shirts','clothing/men/tshirts','clothing/men/pajamas',
        'clothing/men/jackets','clothing/men/underwear',
        'clothing/women/underwear','clothing/women/homewear','clothing/women/pajamas',
        'clothing/women/jackets','clothing/women/tops','clothing/women/abayas',
        'clothing/women/dresses',
        'clothing/kids/sets','clothing/kids/tops','clothing/kids/dresses',
        'clothing/kids/jackets','clothing/kids/bottoms','clothing/kids/underwear',
        'clothing/kids/blankets',

        // --- ساعات ---
        'watches/men/leather','watches/men/metal',
        'watches/women/leather','watches/women/metal',
        'watches/smart',

        // --- إلكترونيات (حسب شجرتك) ---
        'electronics/phones/samsung','electronics/phones/iphone',
        'electronics/phones/xiaomi','electronics/phones/nokia',
        'electronics/phones/oppo','electronics/phones/realme',
        'electronics/computing/pc-accessories',
        'electronics/mobile-acc/audio',

        // --- شنط وإكسسوارات ---
        'bags/women/shoulder','bags/women/laptop','bags/women/accessories-women',
        'bags/school/school','bags/school/stationery',
        'bags/men/wallets-men','bags/men/belts','bags/men/sunglasses',

        // --- الأطفال والألعاب ---
        'kids/games/blocks',

        // --- الرياضة والصحة ---
        'sporthealth/sport/shakers','sporthealth/sport/supplements',
        'sporthealth/sport/home-gym','sporthealth/sport/football','sporthealth/sport/other',
        'sporthealth/health/braces','sporthealth/health/massage','sporthealth/health/personal',
        'sporthealth/health/pillows','sporthealth/health/insoles','sporthealth/health/supplies',

        // --- كتب وروايات ---
        'books/education','books/novels',

    ];

    // نستدعي دوال الديمو من الـ CategoryController بدل ما نكرر الداتا
    $cat = app(CategoryController::class);

   
    foreach ($paths as $p) {
        $items = $cat->demoProducts($p);   // ترجع مصفوفة منتجات لهذا المسار
        foreach ($items as $prod) {
            if (($prod['slug'] ?? null) === $slug) {
                // معلومات إضافية اختيارية
                if (str_starts_with($p, 'shoes')) {
                    $prod['department'] = 'shoes';
                    $prod['gender'] = str_contains($p, '/men/') ? 'men' :
                                      (str_contains($p, '/women/') ? 'women' :
                                      (str_contains($p, '/kids/') ? 'kids' : null));
                } elseif (str_starts_with($p, 'clothing')) {
                    $prod['department'] = 'clothing';
                    $prod['gender'] = str_contains($p, '/men/') ? 'men' :
                                      (str_contains($p, '/women/') ? 'women' :
                                      (str_contains($p, '/kids/') ? 'kids' : null));
                } elseif (str_starts_with($p, 'perfumes')) {
                    $prod['department'] = 'perfumes';
                } elseif (str_starts_with($p, 'beauty')) {
                    $prod['department'] = 'beauty';
                }

                $prod['path'] = $p; // مفيد لرابط الرجوع/الفلاتر
                return $prod;
            }
        }
    }

    return null;
}    /**
     * كتالوج الديمو الموحَّد — يحتوي كل السلالج التي استخدمناها في صفحات التصنيفات.
     * لو أضفت منتج جديد في التصنيفات، أضِف سلاجه هنا.
     */
   

    /* =================== Utils =================== */
    private function inferBrand(string $name = ''): ?string
    {
        $map = [
            'adidas'=>'Adidas','puma'=>'Puma','skechers'=>'Skechers','vans'=>'Vans',
            'golf'=>'GOLF & HORSE','horse'=>'GOLF & HORSE','hush'=>'Hush Puppies',
            'leather'=>'Leather','جلد'=>'Leather','relax foot'=>'Relax Foot','medical'=>'Medical'
        ];
        $low = mb_strtolower($name);
        foreach ($map as $k=>$v) if (str_contains($low, $k)) return $v;
        return null;
    }

    private function inferColor(string $name = ''): ?string
    {
        $pairs = [
            'أبيض'=>'أبيض','white'=>'White',
            'أسود'=>'أسود','black'=>'Black',
            'رمادي'=>'Grey','grey'=>'Grey','gray'=>'Grey',
            'بني'=>'Brown','brown'=>'Brown',
            'عسلي'=>'Beige','beige'=>'Beige','بيج'=>'Beige',
            'كحلي'=>'Navy','navy'=>'Navy',
            'أزرق'=>'Blue','blue'=>'Blue',
            'أحمر'=>'Red','red'=>'Red',
            'أخضر'=>'Green','green'=>'Green',
            'زيتي'=>'Olive','olive'=>'Olive',
        ];
        $low = mb_strtolower($name);
        foreach ($pairs as $k=>$v) if (str_contains($low, mb_strtolower($k))) return $v;
        return null;
    }

    private function prettyFromSlug(string $slug): string
    {
        $s = str_replace(['-', '_'], ' ', $slug);
        $s = preg_replace('/\s+/', ' ', trim($s));
        return mb_convert_case($s, MB_CASE_TITLE, 'UTF-8');
    }
}

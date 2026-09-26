<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\Cart;
use App\Services\CatalogItemResolver;
use App\Services\MarketplaceSettings;
use App\Services\PublicCategoryNavigation;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function index(MarketplaceSettings $settings, PublicCategoryNavigation $categoryNavigation)
    {
        $items    = Cart::items();
        $subtotal = Cart::total();

        $subtotalMinor = $settings->decimalToMinorUnits($subtotal);
        $shipping = $settings->shippingForSubtotal($subtotalMinor) / 100;
        $shippingFlatFee = $settings->moneyInMinorUnits('checkout.shipping.flat_fee') / 100;
        $freeShippingThreshold = $settings->moneyInMinorUnits('checkout.shipping.free_threshold') / 100;
        $grand    = $subtotal + $shipping;
        $checkoutToken = (string) session('checkout.token', Str::uuid());
        session(['checkout.token' => $checkoutToken]);

        return view('cart.index', [
            'items'     => $items,
            'subtotal'  => $subtotal,
            'shipping'  => $shipping,
            'shippingFlatFee' => $shippingFlatFee,
            'freeShippingThreshold' => $freeShippingThreshold,
            'grand'     => $grand,
            'total'     => $grand, // توافق مع القالب
            'checkoutToken' => $checkoutToken,
            'ordersEnabled' => $settings->boolean('site.orders_enabled'),
            'navigationCategories' => $categoryNavigation->roots(),
        ]);
    }

    public function add(Request $request, CatalogItemResolver $resolver)
    {
        $data = $request->validate([
            'id'              => ['nullable','required_without:slug'],
            'slug'            => ['nullable','required_without:id','string','max:200'],
            'offer_id'        => ['nullable','integer'],
            'offer_variant_id' => ['nullable','integer'],
            'qty'             => ['nullable','integer','min:1','max:999'],
            'options.size'    => ['nullable','string','max:100'],
            'options.color'   => ['nullable','string','max:100'],
        ]);

        $item = $resolver->resolve(
            $data['id'] ?? null,
            $data['slug'] ?? null,
            isset($data['offer_id']) ? (int) $data['offer_id'] : null,
            isset($data['offer_variant_id']) ? (int) $data['offer_variant_id'] : null,
            $data['options'] ?? [],
        );
        if (!$item) {
            throw ValidationException::withMessages([
                'cart' => 'المنتج غير موجود أو غير متاح حاليًا.',
            ]);
        }
        if ($item['offer_variant_required'] && ! $item['offer_variant_id']) {
            throw ValidationException::withMessages([
                'offer_variant_id' => 'اختر خيار المنتج المتاح قبل إضافته إلى السلة.',
            ]);
        }

        $qty  = (int)  ($data['qty']  ?? 1);

        $payload = [
            'id'     => $item['id'],
            'product_id' => $item['product_id'],
            'offer_id' => $item['offer_id'],
            'offer_variant_id' => $item['offer_variant_id'],
            'merchant_id' => $item['merchant_id'],
            'location_id' => $item['location_id'],
            'currency' => $item['currency'],
            'compare_at_price' => $item['compare_at_price'],
            'slug'   => $item['slug'],
            'name'   => $item['name'],
            'unit'   => $item['price'],
            'price'  => $item['price'],
            'qty'    => $qty,
            'image'  => $item['image'],
            'options'=> $item['offer_variant_id'] ? $item['options'] : ($data['options'] ?? []),
        ];

        Cart::add($payload);

        // رد JSON للواجهات التي تستخدم fetch/AJAX
        if ($request->expectsJson() || $request->ajax() ||
            $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'ok'    => true,
                'msg'   => 'تمت إضافة المنتج إلى السلة.',
                'count' => Cart::count(),
                'total' => Cart::total(),
            ]);
        }

        return redirect()->route('cart.index')->with('success', 'تمت إضافة المنتج إلى السلة.');
    }

    /**
     * مسار تقليدي: PATCH /cart/{key}
     * يعمل بالمفتاح الداخلي للسلة أو بالـ slug كبديل.
     */
    public function updateQty(Request $request, string $key, MarketplaceSettings $settings)
    {
        $validated = $request->validate([
            'qty' => ['required','integer','min:1','max:999'],
        ]);
        $qty = (int)$validated['qty'];

        // جرّب المفتاح كما هو
        $items = Cart::items();
        $targetKey = null;

        if (array_key_exists($key, $items)) {
            $targetKey = $key;
        } else {
            // بديل: البحث بالـ slug
            foreach ($items as $k => $it) {
                if (($it['slug'] ?? null) === $key) {
                    $targetKey = $k;
                    break;
                }
            }
        }

        if ($targetKey === null) {
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => 'Item not found'], 404);
            }
            return back()->withErrors(['cart' => 'العنصر غير موجود في السلة']);
        }

        Cart::update($targetKey, $qty);

        // حسابات سريعة للرد
        $newItems = Cart::items();
        $line     = $newItems[$targetKey] ?? null;
        $unit     = (float)($line['unit'] ?? $line['price'] ?? 0);
        $lineTot  = $unit * (int)($line['qty'] ?? 1);

        $subtotal = Cart::total();
        $shipping = $settings->shippingForSubtotal(
            $settings->decimalToMinorUnits($subtotal)
        ) / 100;
        $grand    = $subtotal + $shipping;

        if ($request->wantsJson()) {
            return response()->json([
                'ok'         => true,
                'count'      => Cart::count(),
                'line_total' => round($lineTot, 2),
                'subtotal'   => round($subtotal, 2),
                'shipping'   => round($shipping, 2),
                'grand'      => round($grand, 2),
            ]);
        }

        return back()->with('success', 'تم تحديث الكمية.');
    }

    // مسار بديل قديم: يستدعي updateQty
    public function update(Request $request, string $id, MarketplaceSettings $settings)
    {
        return $this->updateQty($request, $id, $settings);
    }

    public function remove(string $id)
    {
        Cart::remove($id);
        return redirect()->route('cart.index')->with('success', 'تم حذف المنتج من السلة.');
    }

    public function clear()
    {
        Cart::clear();
        return redirect()->route('cart.index')->with('success', 'تم تفريغ السلة.');
    }

    public function count()
    {
        return response()->json(['count' => Cart::count()]);
    }
}

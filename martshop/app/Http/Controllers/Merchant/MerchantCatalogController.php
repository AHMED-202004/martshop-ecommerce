<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\StoreProductOfferRequest;
use App\Http\Requests\Merchant\UpdateProductOfferRequest;
use App\Http\Requests\Merchant\PauseProductOfferRequest;
use App\Http\Requests\Merchant\ResumeProductOfferRequest;
use App\Http\Requests\Merchant\UpdateProductSubmissionRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Services\MerchantCatalogService;
use App\Services\ProductOfferLifecycleService;
use App\Services\MerchantProductSubmissionService;
use Illuminate\Http\Request;

class MerchantCatalogController extends Controller
{
    public function index(Request $request)
    {
        $merchant = $request->user()->merchant()
            ->select(['id', 'user_id', 'verification_status'])
            ->first();
        if (! $merchant) {
            return redirect()->route('merchant.profile.edit')
                ->withErrors(['merchant' => 'أنشئ ملف التاجر أولًا.']);
        }

        $offers = $merchant->offers()
            ->select([
                'id', 'product_id', 'merchant_id', 'location_id', 'price', 'stock',
                'status', 'review_notes', 'lock_version', 'paused_by', 'pause_reason',
                'submitted_at',
            ])
            ->with([
                'product:id,name,slug,status,created_by_merchant_id,review_notes',
                'product.changeRequests' => fn ($query) => $query
                    ->select(['id', 'product_id', 'merchant_id', 'status', 'open_key'])
                    ->where('merchant_id', $merchant->id)
                    ->whereNotNull('open_key')
                    ->latest('id'),
                'location:id,name',
            ])
            ->latest('submitted_at')
            ->latest('id')
            ->paginate(30);

        return view('merchant.catalog.index', compact('merchant', 'offers'));
    }

    public function create(Request $request)
    {
        $this->authorize('createOffer', Product::class);
        $merchant = $request->user()->merchant;
        $existingProductIds = $merchant->offers()->pluck('product_id');

        return view('merchant.catalog.create', [
            'products' => Product::query()
                ->where('status', 'active')
                ->inPublicCategoryOrUncategorized()
                ->whereNotIn('id', $existingProductIds)
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'categories' => Category::query()->publiclyVisible()->orderBy('path')->get(['id', 'name', 'path']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'locations' => Location::query()->where('is_active', true)
                ->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreProductOfferRequest $request, MerchantCatalogService $catalogue)
    {
        $offer = $catalogue->submit(
            $request->user()->merchant,
            $request->user(),
            $request->validated(),
            $request->file('image'),
        );

        return redirect()->route('merchant.catalog.index')
            ->with('success', "تم إرسال العرض رقم {$offer->id} للمراجعة.");
    }

    public function edit(Request $request, ProductOffer $offer)
    {
        $this->authorize('update', $offer);
        $offer->load([
            'product:id,name,slug,status',
            'location:id,name',
            'variants' => fn ($query) => $query->active()->orderBy('id'),
        ]);

        return view('merchant.catalog.edit', [
            'offer' => $offer,
            'locations' => Location::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'variantSizes' => $offer->variants
                ->map(fn ($variant) => $variant->attributes['size'] ?? null)
                ->filter()
                ->implode(', '),
            'variantColor' => $offer->variants
                ->map(fn ($variant) => $variant->attributes['color'] ?? null)
                ->filter()
                ->first(),
        ]);
    }

    public function update(
        UpdateProductOfferRequest $request,
        ProductOffer $offer,
        ProductOfferLifecycleService $lifecycle,
    ) {
        $lifecycle->update(
            $offer,
            $request->user()->merchant,
            $request->user(),
            $request->validated(),
        );

        return redirect()->route('merchant.catalog.index')
            ->with('success', 'تم تحديث العرض بنجاح.');
    }

    public function pause(
        PauseProductOfferRequest $request,
        ProductOffer $offer,
        ProductOfferLifecycleService $lifecycle,
    ) {
        $lifecycle->pause(
            $offer,
            $request->user()->merchant,
            $request->user(),
            (int) $request->validated('lock_version'),
            $request->validated('reason'),
        );

        return back()->with('success', 'تم إيقاف العرض.');
    }

    public function resume(
        ResumeProductOfferRequest $request,
        ProductOffer $offer,
        ProductOfferLifecycleService $lifecycle,
    ) {
        $lifecycle->resume(
            $offer,
            $request->user()->merchant,
            $request->user(),
            (int) $request->validated('lock_version'),
        );

        return back()->with('success', 'تم استئناف العرض.');
    }

    public function editProduct(Request $request, Product $product)
    {
        $this->authorize('updateSubmission', $product);
        $product = Product::query()->select([
            'id', 'brand_id', 'category_id', 'created_by_merchant_id', 'name', 'description',
            'model', 'image', 'submission_image_path', 'status', 'review_notes',
        ])->whereKey($product->id)->firstOrFail();

        return view('merchant.catalog.product-edit', [
            'product' => $product,
            'categories' => Category::query()->active()->orderBy('path')->get(['id', 'name', 'path']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function updateProduct(
        UpdateProductSubmissionRequest $request,
        Product $product,
        MerchantProductSubmissionService $submissions,
    ) {
        $submissions->resubmit(
            $product,
            $request->user()->merchant,
            $request->user(),
            $request->validated(),
            $request->file('image'),
        );

        return redirect()->route('merchant.catalog.index')
            ->with('success', 'تم تصحيح المنتج وإعادة إرساله للمراجعة.');
    }
}

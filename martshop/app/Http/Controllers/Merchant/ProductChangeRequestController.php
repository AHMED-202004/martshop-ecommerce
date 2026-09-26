<?php

namespace App\Http\Controllers\Merchant;

use App\Enums\ProductChangeRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\StoreProductChangeRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductChangeRequest;
use App\Services\ProductChangeRequestService;
use Illuminate\Http\Request;

class ProductChangeRequestController extends Controller
{
    public function create(Request $request, Product $product)
    {
        $this->authorize('proposeChanges', $product);
        $product = Product::query()->select([
            'id', 'brand_id', 'category_id', 'name', 'description', 'model',
            'specifications', 'status',
        ])->whereKey($product->id)->firstOrFail();
        $merchant = $request->user()->merchant;
        $changeRequest = ProductChangeRequest::query()
            ->select([
                'id', 'product_id', 'merchant_id', 'proposed_changes', 'proposed_image_path',
                'status', 'open_key', 'review_notes', 'lock_version',
            ])
            ->where('product_id', $product->id)
            ->where('merchant_id', $merchant->id)
            ->whereNotNull('open_key')
            ->latest('id')
            ->first();

        if ($changeRequest?->status === ProductChangeRequestStatus::PendingReview) {
            return redirect()->route('merchant.catalog.index')
                ->withErrors(['change_request' => 'اقتراح التعديل ما زال قيد المراجعة.']);
        }

        return view('merchant.catalog.change-request', [
            'product' => $product,
            'changeRequest' => $changeRequest,
            'changes' => $changeRequest?->proposed_changes ?? [],
            'categories' => Category::query()->active()->orderBy('path')->get(['id', 'name', 'path']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(
        StoreProductChangeRequest $request,
        Product $product,
        ProductChangeRequestService $service,
    ) {
        $changeRequest = $service->submit(
            $product,
            $request->user()->merchant,
            $request->user(),
            $request->validated(),
            $request->file('image'),
        );

        return redirect()->route('merchant.catalog.index')
            ->with('success', "تم إرسال اقتراح التغيير رقم {$changeRequest->id} للمراجعة.");
    }
}

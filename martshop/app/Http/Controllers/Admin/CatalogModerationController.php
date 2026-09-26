<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductOfferStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductChangeRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ModerateProductOfferRequest;
use App\Http\Requests\Admin\ModerateProductRequest;
use App\Http\Requests\Admin\ModerateProductChangeRequest;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\ProductChangeRequest;
use App\Services\CatalogModerationService;
use App\Services\ProductChangeRequestService;
use Illuminate\Http\Request;

class CatalogModerationController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('products.moderate'), 403);
        $reviewerMerchantId = $request->user()->merchant?->id;

        $products = Product::query()
            ->select([
                'id', 'brand_id', 'category_id', 'created_by_merchant_id', 'name',
                'description', 'image', 'submission_image_path', 'status', 'submitted_at',
            ])
            ->whereNotNull('created_by_merchant_id')
            ->when($reviewerMerchantId, fn ($query, $merchantId) => $query
                ->where('created_by_merchant_id', '!=', $merchantId)
                ->whereDoesntHave('offers', fn ($offers) => $offers->where('merchant_id', $merchantId)))
            ->whereIn('status', [
                ProductStatus::PendingReview->value,
                ProductStatus::ChangesRequested->value,
            ])
            ->with(['creatorMerchant:id,user_id', 'creatorMerchant.user:id,name', 'category:id,name,path', 'brand:id,name'])
            ->latest('submitted_at')
            ->paginate(20, pageName: 'products_page');

        $offers = ProductOffer::query()
            ->select([
                'id', 'product_id', 'merchant_id', 'location_id', 'price', 'stock',
                'status', 'preparation_time_days', 'submitted_at',
            ])
            ->whereNotNull('merchant_id')
            ->when($reviewerMerchantId, fn ($query, $merchantId) => $query->where('merchant_id', '!=', $merchantId))
            ->whereIn('status', [
                ProductOfferStatus::PendingReview->value,
                ProductOfferStatus::ChangesRequested->value,
            ])
            ->with([
                'product:id,name,status', 'merchant:id,user_id', 'merchant.user:id,name',
                'location:id,name', 'variants:id,product_offer_id,attributes',
            ])
            ->latest('submitted_at')
            ->paginate(20, pageName: 'offers_page');

        $changeRequests = ProductChangeRequest::query()
            ->select([
                'id', 'product_id', 'merchant_id', 'proposed_changes', 'proposed_image_path',
                'status', 'submitted_at',
            ])
            ->where('status', ProductChangeRequestStatus::PendingReview->value)
            ->when($reviewerMerchantId, fn ($query, $merchantId) => $query->where('merchant_id', '!=', $merchantId))
            ->with(['product:id,name,category_id,brand_id,model', 'merchant:id,user_id', 'merchant.user:id,name'])
            ->latest('submitted_at')
            ->paginate(20, pageName: 'changes_page');

        return view('admin.catalog.index', compact('products', 'offers', 'changeRequests'));
    }

    public function updateProduct(
        ModerateProductRequest $request,
        Product $product,
        CatalogModerationService $moderation,
    ) {
        $this->authorize('moderate', $product);
        $moderation->reviewProduct(
            $product,
            ProductStatus::from($request->validated('decision')),
            $request->user(),
            $request->validated('reason'),
        );

        return back()->with('success', 'تم تحديث حالة المنتج.');
    }

    public function updateOffer(
        ModerateProductOfferRequest $request,
        ProductOffer $offer,
        CatalogModerationService $moderation,
    ) {
        $this->authorize('moderate', $offer);
        $moderation->reviewOffer(
            $offer,
            ProductOfferStatus::from($request->validated('decision')),
            $request->user(),
            $request->validated('reason'),
        );

        return back()->with('success', 'تم تحديث حالة العرض.');
    }

    public function updateChangeRequest(
        ModerateProductChangeRequest $request,
        ProductChangeRequest $changeRequest,
        ProductChangeRequestService $service,
    ) {
        $this->authorize('moderate', $changeRequest);
        $service->review(
            $changeRequest,
            ProductChangeRequestStatus::from($request->validated('decision')),
            $request->user(),
            $request->validated('reason'),
        );

        return back()->with('success', 'تم تحديث طلب تغيير المنتج.');
    }
}

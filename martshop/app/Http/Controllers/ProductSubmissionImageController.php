<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\AuditLogger;
use App\Services\ProductSubmissionImageStorage;

class ProductSubmissionImageController extends Controller
{
    public function show(Product $product, ProductSubmissionImageStorage $images, AuditLogger $audit)
    {
        $this->authorize('viewSubmissionImage', $product);
        $image = $images->resolve($product);
        abort_unless($image, 404);

        $audit->record('catalog.product_submission_image_viewed', $product, metadata: [
            'merchant_id' => $product->created_by_merchant_id,
        ]);
        $contentType = match ($image['extension']) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        };

        return response()->file($image['path'], [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline; filename="product-submission-'.$product->id.'.'.$image['extension'].'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}

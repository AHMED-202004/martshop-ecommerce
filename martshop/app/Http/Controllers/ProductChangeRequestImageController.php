<?php

namespace App\Http\Controllers;

use App\Models\ProductChangeRequest;
use App\Services\AuditLogger;
use App\Services\ProductChangeRequestImageStorage;

class ProductChangeRequestImageController extends Controller
{
    public function show(
        ProductChangeRequest $changeRequest,
        ProductChangeRequestImageStorage $images,
        AuditLogger $audit,
    )
    {
        $this->authorize('view', $changeRequest);
        $image = $images->resolve($changeRequest);
        abort_unless($image, 404);

        $audit->record('catalog.product_change_image_viewed', $changeRequest, metadata: [
            'merchant_id' => $changeRequest->merchant_id,
        ]);

        return response()->download(
            $image['path'],
            'product-change-image-'.$changeRequest->id.'.'.$image['extension'],
            [
                'Content-Type' => 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
        );
    }
}

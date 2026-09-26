<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MerchantDocumentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewMerchantDocumentRequest;
use App\Models\MerchantDocument;
use App\Services\MerchantVerificationService;

class MerchantDocumentReviewController extends Controller
{
    public function update(
        ReviewMerchantDocumentRequest $request,
        MerchantDocument $merchantDocument,
        MerchantVerificationService $verification,
    ) {
        $verification->reviewDocument(
            $merchantDocument,
            MerchantDocumentStatus::from($request->validated('status')),
            $request->user(),
            $request->validated('review_notes'),
        );

        return back()->with('success', 'تم تحديث مراجعة المستند.');
    }
}

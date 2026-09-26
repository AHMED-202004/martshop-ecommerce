<?php

namespace App\Http\Controllers;

use App\Models\RefundTransfer;
use App\Services\AuditLogger;
use App\Services\PrivateFileIntegrityVerifier;
use Illuminate\Support\Facades\Gate;

class RefundTransferProofController extends Controller
{
    public function show(RefundTransfer $refundTransfer, AuditLogger $audit, PrivateFileIntegrityVerifier $files)
    {
        Gate::authorize('view', $refundTransfer);
        abort_unless($refundTransfer->paid_at && $refundTransfer->proof_path, 404);
        $relativePath = str_replace('\\', '/', $refundTransfer->proof_path);
        $filePath = $files->verify(
            $relativePath,
            'refund-proofs/'.$refundTransfer->id,
            '[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}\.(?:jpe?g|png|webp|pdf)',
            $refundTransfer->proof_size,
            $refundTransfer->proof_sha256,
            10 * 1024 * 1024,
            1,
        );
        abort_unless($filePath, 404);
        $audit->record('refund.proof_viewed', $refundTransfer);

        return response()->download($filePath, 'refund-proof-'.$refundTransfer->id.'.'.pathinfo($relativePath, PATHINFO_EXTENSION), [
            'Content-Type' => 'application/octet-stream', 'Cache-Control' => 'no-store, private',
            'Referrer-Policy' => 'no-referrer', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

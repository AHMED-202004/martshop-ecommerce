<?php

namespace App\Http\Controllers;

use App\Models\PaymentProof;
use App\Services\AuditLogger;
use App\Services\PrivateFileIntegrityVerifier;

class PaymentProofController extends Controller
{
    public function show(PaymentProof $paymentProof, AuditLogger $audit, PrivateFileIntegrityVerifier $files)
    {
        $paymentProof->load('payment.order');
        $this->authorize('view', $paymentProof->payment);

        abort_unless($paymentProof->disk === 'local', 404);
        $relativePath = str_replace('\\', '/', $paymentProof->path);
        $filePath = $files->verify(
            $relativePath,
            'payment-proofs/'.$paymentProof->payment_id,
            '[a-f0-9-]+\.(?:jpe?g|png|webp|pdf)',
            $paymentProof->size,
            $paymentProof->sha256,
        );
        abort_unless($filePath, 404);
        $audit->record('payment.proof_viewed', $paymentProof->payment, metadata: [
            'payment_proof_id' => $paymentProof->id,
        ]);

        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        $name = 'payment-proof-'.$paymentProof->id.'.'.$extension;

        return response()->download($filePath, $name, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}

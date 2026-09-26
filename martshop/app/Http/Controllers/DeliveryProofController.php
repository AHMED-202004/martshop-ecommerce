<?php

namespace App\Http\Controllers;

use App\Models\DeliveryProof;
use App\Services\AuditLogger;
use App\Services\PrivateFileIntegrityVerifier;

class DeliveryProofController extends Controller
{
    public function show(DeliveryProof $deliveryProof, AuditLogger $audit, PrivateFileIntegrityVerifier $files)
    {
        $deliveryProof->load('delivery');
        $this->authorize('view', $deliveryProof->delivery);
        abort_unless($deliveryProof->disk === 'local', 404);
        $relativePath = str_replace('\\', '/', $deliveryProof->path);
        $filePath = $files->verify(
            $relativePath,
            'delivery-proofs/'.$deliveryProof->delivery_id,
            '[0-9A-HJKMNP-TV-Z]{26}\.(?:jpe?g|png)',
            $deliveryProof->size,
            $deliveryProof->sha256,
        );
        abort_unless($filePath, 404);
        $audit->record('delivery.proof_viewed', $deliveryProof->delivery, metadata: [
            'delivery_proof_id' => $deliveryProof->id,
        ]);
        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

        return response()->download($filePath, 'delivery-proof-'.$deliveryProof->id.'.'.$extension, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}

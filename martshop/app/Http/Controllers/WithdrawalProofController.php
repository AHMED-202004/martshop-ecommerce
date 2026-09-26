<?php

namespace App\Http\Controllers;

use App\Models\WithdrawalProof;
use App\Services\AuditLogger;
use App\Services\PrivateFileIntegrityVerifier;

class WithdrawalProofController extends Controller
{
    public function show(WithdrawalProof $withdrawalProof, AuditLogger $audit, PrivateFileIntegrityVerifier $files)
    {
        $withdrawalProof->load('withdrawal.merchant');
        $this->authorize('view', $withdrawalProof->withdrawal);

        abort_unless($withdrawalProof->disk === 'local', 404);
        $relativePath = str_replace('\\', '/', $withdrawalProof->path);
        $filePath = $files->verify(
            $relativePath,
            'withdrawal-proofs/'.$withdrawalProof->withdrawal_request_id,
            '[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}\.(?:jpe?g|png|webp|pdf)',
            $withdrawalProof->size,
            $withdrawalProof->sha256,
        );
        abort_unless($filePath, 404);
        $audit->record('withdrawal.proof_viewed', $withdrawalProof->withdrawal, metadata: [
            'withdrawal_proof_id' => $withdrawalProof->id,
        ]);

        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        $name = 'withdrawal-proof-'.$withdrawalProof->id.'.'.$extension;

        return response()->download($filePath, $name, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}

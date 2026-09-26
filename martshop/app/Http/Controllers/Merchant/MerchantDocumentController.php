<?php

namespace App\Http\Controllers\Merchant;

use App\Enums\MerchantDocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\UploadMerchantDocumentRequest;
use App\Models\MerchantDocument;
use App\Services\AuditLogger;
use App\Services\MerchantDocumentStorage;
use Illuminate\Support\Facades\Storage;

class MerchantDocumentController extends Controller
{
    public function store(UploadMerchantDocumentRequest $request, MerchantDocumentStorage $storage)
    {
        $storage->store(
            $request->user()->merchant()->firstOrFail(),
            MerchantDocumentType::from($request->validated('type')),
            $request->file('document'),
            $request->user(),
        );

        return redirect()->route('merchant.profile.edit')->with('success', 'تم رفع المستند بصورة خاصة وآمنة.');
    }

    public function show(MerchantDocument $merchantDocument, AuditLogger $audit)
    {
        $this->authorize('view', $merchantDocument);
        abort_unless($merchantDocument->disk === 'local', 404);
        $disk = Storage::disk('local');
        $relativePath = str_replace('\\', '/', $merchantDocument->path);
        $expectedPrefix = 'merchant-documents/'.$merchantDocument->merchant_id.'/';
        abort_unless(
            preg_match('/\A'.preg_quote($expectedPrefix, '/').'[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}\.(?:jpe?g|png|pdf)\z/i', $relativePath)
            && $disk->exists($relativePath),
            404,
        );

        $basePath = realpath($disk->path('merchant-documents/'.$merchantDocument->merchant_id));
        $filePath = realpath($disk->path($relativePath));
        $normalizedBase = $basePath ? rtrim(str_replace('\\', '/', $basePath), '/').'/' : null;
        $normalizedFile = $filePath ? str_replace('\\', '/', $filePath) : null;
        abort_unless(
            $normalizedBase && $normalizedFile && str_starts_with($normalizedFile, $normalizedBase)
            && is_file($filePath) && is_int($merchantDocument->size)
            && filesize($filePath) === $merchantDocument->size,
            404,
        );

        if ($merchantDocument->sha256 !== null) {
            $actualHash = hash_file('sha256', $filePath);
            abort_unless(
                preg_match('/\A[a-f0-9]{64}\z/i', $merchantDocument->sha256)
                && is_string($actualHash)
                && hash_equals(strtolower($merchantDocument->sha256), strtolower($actualHash)),
                404,
            );
        }

        $audit->record('merchant.document_viewed', $merchantDocument, metadata: [
            'merchant_id' => $merchantDocument->merchant_id,
        ]);
        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

        return response()->download($filePath, 'merchant-document-'.$merchantDocument->id.'.'.$extension, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}

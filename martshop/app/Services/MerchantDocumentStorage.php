<?php

namespace App\Services;

use App\Enums\MerchantDocumentStatus;
use App\Enums\MerchantDocumentType;
use App\Models\Merchant;
use App\Models\MerchantDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class MerchantDocumentStorage
{
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'application/pdf'];
    private const MAX_BYTES = 10 * 1024 * 1024;

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function store(
        Merchant $merchant,
        MerchantDocumentType $type,
        UploadedFile $file,
        User $actor,
    ): MerchantDocument
    {
        $merchant = Merchant::query()->whereKey($merchant->id)->firstOrFail();
        abort_unless($actor->can('update', $merchant), 403);

        if (!in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES, true) || $file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'document' => 'يجب أن يكون المستند JPG أو PNG أو PDF وبحجم لا يتجاوز 10MB.',
            ]);
        }

        $extension = $file->guessExtension() ?: 'bin';
        $filename = Str::uuid().'.'.$extension;
        $path = Storage::disk('local')->putFileAs(
            "merchant-documents/{$merchant->getKey()}",
            $file,
            $filename,
        );
        $storedFile = is_string($path) ? Storage::disk('local')->path($path) : null;
        $storedSize = $storedFile && is_file($storedFile) ? filesize($storedFile) : false;
        $sha256 = $storedFile && is_file($storedFile) ? hash_file('sha256', $storedFile) : false;
        if (! is_string($path) || ! is_int($storedSize) || ! is_string($sha256)) {
            if (is_string($path)) {
                Storage::disk('local')->delete($path);
            }
            throw ValidationException::withMessages(['document' => 'تعذر التحقق من سلامة المستند.']);
        }

        try {
            $document = $merchant->documents()->create([
                'type' => $type,
                'disk' => 'local',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $storedSize,
                'sha256' => $sha256,
                'status' => MerchantDocumentStatus::Pending,
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }

        $this->audit->record('merchant.document_uploaded', $document, metadata: [
            'merchant_id' => $merchant->getKey(),
            'type' => $type->value,
        ]);

        return $document;
    }
}

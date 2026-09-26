<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\ProductChangeRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ProductChangeRequestImageStorage
{
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    private const MAX_BYTES = 5 * 1024 * 1024;

    /**
     * @return array{disk: string, path: string, size: int, sha256: string}
     */
    public function store(Merchant $merchant, UploadedFile $image): array
    {
        $mime = $image->getMimeType();
        $size = $image->getSize();
        if (! isset(self::MIME_EXTENSIONS[$mime]) || ! is_int($size) || $size > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'image' => 'يجب أن تكون صورة المنتج JPG أو PNG أو WEBP وبحجم لا يتجاوز 5MB.',
            ]);
        }
        $extension = self::MIME_EXTENSIONS[$mime];

        $path = $image->storeAs(
            'product-change-requests/'.$merchant->getKey(),
            Str::uuid().'.'.$extension,
            'local',
        );
        if (! is_string($path)) {
            throw new RuntimeException('Unable to store the proposed product image.');
        }

        $storedFile = Storage::disk('local')->path($path);
        $size = is_file($storedFile) ? filesize($storedFile) : false;
        $sha256 = is_file($storedFile) ? hash_file('sha256', $storedFile) : false;
        if (! is_int($size) || ! is_string($sha256)) {
            Storage::disk('local')->delete($path);
            throw new RuntimeException('Unable to verify the stored proposed product image.');
        }

        return ['disk' => 'local', 'path' => $path, 'size' => $size, 'sha256' => $sha256];
    }

    /**
     * @return array{path: string, relative_path: string, extension: string}|null
     */
    public function resolve(ProductChangeRequest $changeRequest): ?array
    {
        if ($changeRequest->proposed_image_disk !== 'local' || ! is_string($changeRequest->proposed_image_path)) {
            return null;
        }

        $disk = Storage::disk('local');
        $relativePath = str_replace('\\', '/', $changeRequest->proposed_image_path);
        $extensionPattern = '(?:jpe?g|png|webp)';
        $merchantPrefix = 'product-change-requests/'.$changeRequest->merchant_id.'/';
        $isCurrentPath = preg_match(
            '/\A'.preg_quote($merchantPrefix, '/').'[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}\.'.$extensionPattern.'\z/i',
            $relativePath,
        ) === 1;
        $isLegacyPath = preg_match(
            '/\Aproduct-change-requests\/[A-Za-z0-9]{40}\.'.$extensionPattern.'\z/',
            $relativePath,
        ) === 1;
        if ((! $isCurrentPath && ! $isLegacyPath) || ! $disk->exists($relativePath)) {
            return null;
        }

        $basePath = realpath($disk->path($isCurrentPath ? rtrim($merchantPrefix, '/') : 'product-change-requests'));
        $filePath = realpath($disk->path($relativePath));
        $normalizedBase = $basePath ? rtrim(str_replace('\\', '/', $basePath), '/').'/' : null;
        $normalizedFile = $filePath ? str_replace('\\', '/', $filePath) : null;
        if (! $normalizedBase || ! $normalizedFile || ! str_starts_with($normalizedFile, $normalizedBase) || ! is_file($filePath)) {
            return null;
        }

        $size = $changeRequest->proposed_image_size;
        $sha256 = $changeRequest->proposed_image_sha256;
        $hasCompleteIntegrityMetadata = is_int($size)
            && is_string($sha256)
            && preg_match('/\A[a-f0-9]{64}\z/i', $sha256) === 1;
        if ($isCurrentPath && ! $hasCompleteIntegrityMetadata) {
            return null;
        }
        if (($size !== null || $sha256 !== null) && ! $hasCompleteIntegrityMetadata) {
            return null;
        }
        if ($hasCompleteIntegrityMetadata) {
            $actualHash = hash_file('sha256', $filePath);
            if (filesize($filePath) !== $size
                || ! is_string($actualHash)
                || ! hash_equals(strtolower($sha256), strtolower($actualHash))) {
                return null;
            }
        }

        return [
            'path' => $filePath,
            'relative_path' => $relativePath,
            'extension' => strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)),
        ];
    }
}

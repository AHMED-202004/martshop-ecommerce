<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ProductSubmissionImageStorage
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
            'product-submissions/'.$merchant->getKey(),
            Str::uuid().'.'.$extension,
            'local',
        );
        if (! is_string($path)) {
            throw new RuntimeException('Unable to store the submitted product image.');
        }

        $storedFile = Storage::disk('local')->path($path);
        $size = is_file($storedFile) ? filesize($storedFile) : false;
        $sha256 = is_file($storedFile) ? hash_file('sha256', $storedFile) : false;
        if (! is_int($size) || ! is_string($sha256)) {
            Storage::disk('local')->delete($path);
            throw new RuntimeException('Unable to verify the submitted product image.');
        }

        return ['disk' => 'local', 'path' => $path, 'size' => $size, 'sha256' => $sha256];
    }

    /**
     * @return array{path: string, relative_path: string, extension: string}|null
     */
    public function resolve(Product $product): ?array
    {
        if ($product->submission_image_disk !== 'local'
            || ! is_string($product->submission_image_path)
            || ! is_int($product->created_by_merchant_id)) {
            return null;
        }

        $disk = Storage::disk('local');
        $relativePath = str_replace('\\', '/', $product->submission_image_path);
        $prefix = 'product-submissions/'.$product->created_by_merchant_id.'/';
        if (preg_match(
            '/\A'.preg_quote($prefix, '/').'[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}\.(?:jpe?g|png|webp)\z/i',
            $relativePath,
        ) !== 1 || ! $disk->exists($relativePath)) {
            return null;
        }

        $basePath = realpath($disk->path(rtrim($prefix, '/')));
        $filePath = realpath($disk->path($relativePath));
        $normalizedBase = $basePath ? rtrim(str_replace('\\', '/', $basePath), '/').'/' : null;
        $normalizedFile = $filePath ? str_replace('\\', '/', $filePath) : null;
        if (! $normalizedBase || ! $normalizedFile || ! str_starts_with($normalizedFile, $normalizedBase)
            || ! is_file($filePath) || ! is_int($product->submission_image_size)
            || filesize($filePath) !== $product->submission_image_size
            || ! is_string($product->submission_image_sha256)
            || preg_match('/\A[a-f0-9]{64}\z/i', $product->submission_image_sha256) !== 1) {
            return null;
        }

        $actualHash = hash_file('sha256', $filePath);
        if (! is_string($actualHash)
            || ! hash_equals(strtolower($product->submission_image_sha256), strtolower($actualHash))) {
            return null;
        }

        return [
            'path' => $filePath,
            'relative_path' => $relativePath,
            'extension' => strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)),
        ];
    }
}

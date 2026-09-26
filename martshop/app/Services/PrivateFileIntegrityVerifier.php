<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class PrivateFileIntegrityVerifier
{
    public function verify(
        string $path,
        string $directory,
        string $filenamePattern,
        mixed $expectedSize,
        mixed $expectedSha256,
        ?int $maximumSize = null,
        int $minimumSize = 0,
    ): ?string {
        $relativePath = str_replace('\\', '/', $path);
        $directory = trim(str_replace('\\', '/', $directory), '/');
        $expectedPrefix = $directory.'/';

        if (! is_int($expectedSize) || $expectedSize < $minimumSize || ($maximumSize !== null && $expectedSize > $maximumSize)
            || ! is_string($expectedSha256) || preg_match('/\A[a-f0-9]{64}\z/i', $expectedSha256) !== 1
            || preg_match('/\A'.preg_quote($expectedPrefix, '/').'(?:'.$filenamePattern.')\z/i', $relativePath) !== 1) {
            return null;
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($relativePath)) {
            return null;
        }

        $basePath = realpath($disk->path($directory));
        $filePath = realpath($disk->path($relativePath));
        $normalizedBase = $basePath ? rtrim(str_replace('\\', '/', $basePath), '/').'/' : null;
        $normalizedFile = $filePath ? str_replace('\\', '/', $filePath) : null;

        if (! $normalizedBase || ! $normalizedFile || ! str_starts_with($normalizedFile, $normalizedBase)
            || ! is_file($filePath) || filesize($filePath) !== $expectedSize) {
            return null;
        }

        $actualHash = hash_file('sha256', $filePath);

        return is_string($actualHash) && hash_equals(strtolower($expectedSha256), strtolower($actualHash))
            ? $filePath
            : null;
    }
}

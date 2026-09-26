<?php

namespace App\Console\Commands;

use App\Models\MerchantDocument;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BackfillMerchantDocumentIntegrity extends Command
{
    protected $signature = 'merchant-documents:backfill-integrity
        {--apply : Persist verified SHA-256 values}
        {--limit=500 : Maximum documents to inspect}';

    protected $description = 'Verify legacy private merchant documents and optionally backfill missing SHA-256 values';

    public function handle(AuditLogger $audit): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1 || $limit > 5000) {
            $this->error('Limit must be between 1 and 5000.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $eligible = 0;
        $updated = 0;
        $invalid = 0;

        MerchantDocument::query()->whereNull('sha256')->orderBy('id')->limit($limit)
            ->each(function (MerchantDocument $document) use ($apply, $audit, &$eligible, &$updated, &$invalid): void {
                $hash = $this->verifiedHash($document);
                if ($hash === null) {
                    $invalid++;

                    return;
                }

                $eligible++;
                if (! $apply) {
                    return;
                }

                $didUpdate = DB::transaction(function () use ($document, $hash, $audit): bool {
                    $locked = MerchantDocument::query()->whereKey($document->id)
                        ->whereNull('sha256')->lockForUpdate()->first();
                    if (! $locked || $this->verifiedHash($locked) !== $hash) {
                        return false;
                    }

                    $locked->update(['sha256' => $hash]);
                    $audit->record(
                        'merchant.document_integrity_backfilled',
                        $locked,
                        after: ['integrity_verified' => true],
                        reason: 'Verified from the private stored file by the maintenance command.',
                    );

                    return true;
                }, 3);
                $updated += (int) $didUpdate;
            });

        $mode = $apply ? 'apply' : 'dry-run';
        $this->info("Mode: {$mode}; eligible: {$eligible}; updated: {$updated}; invalid: {$invalid}.");

        return $invalid === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function verifiedHash(MerchantDocument $document): ?string
    {
        if ($document->disk !== 'local' || ! is_string($document->path) || ! is_numeric($document->size)) {
            return null;
        }

        $relativePath = str_replace('\\', '/', $document->path);
        $prefix = 'merchant-documents/'.$document->merchant_id.'/';
        if (preg_match(
            '/\A'.preg_quote($prefix, '/').'[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}\.(?:jpe?g|png|pdf)\z/i',
            $relativePath,
        ) !== 1) {
            return null;
        }

        $disk = Storage::disk('local');
        $basePath = realpath($disk->path(rtrim($prefix, '/')));
        $filePath = realpath($disk->path($relativePath));
        $normalizedBase = $basePath ? rtrim(str_replace('\\', '/', $basePath), '/').'/' : null;
        $normalizedFile = $filePath ? str_replace('\\', '/', $filePath) : null;
        if (! $normalizedBase || ! $normalizedFile || ! str_starts_with($normalizedFile, $normalizedBase)
            || ! is_file($filePath) || filesize($filePath) !== (int) $document->size) {
            return null;
        }

        $hash = hash_file('sha256', $filePath);

        return is_string($hash) ? $hash : null;
    }
}

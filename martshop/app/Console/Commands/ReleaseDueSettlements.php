<?php

namespace App\Console\Commands;

use App\Models\Delivery;
use App\Services\MarketplaceSettings;
use App\Services\MerchantLedgerService;
use Illuminate\Console\Command;
use Throwable;

class ReleaseDueSettlements extends Command
{
    protected $signature = 'settlements:release-due {--limit=100}';
    protected $description = 'Release eligible delivered sales after the snapshotted dispute deadline.';

    public function handle(MarketplaceSettings $settings, MerchantLedgerService $ledger): int
    {
        if (! $settings->boolean('settlement.auto_release_enabled')) {
            $this->info('Automatic settlement release is disabled.');
            return self::SUCCESS;
        }
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1 || $limit > 1000) {
            $this->error('Limit must be between 1 and 1000.');
            return self::FAILURE;
        }
        $deliveries = Delivery::query()->where('status', 'delivered')->whereNull('settled_at')
            ->whereNotNull('settlement_due_at')->where('settlement_due_at', '<=', now())
            ->whereDoesntHave('dispute', fn ($query) => $query->whereIn('status', ['open', 'refunded']))
            ->orderBy('id')->limit($limit)->get();
        $failed = 0;
        foreach ($deliveries as $delivery) {
            try {
                $ledger->releaseDeliveredSale($delivery);
            } catch (Throwable $exception) {
                report($exception);
                $this->warn('Settlement requires review: delivery #'.$delivery->id);
                $failed++;
            }
        }
        $this->info('Released: '.($deliveries->count() - $failed).'; failed: '.$failed);

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}

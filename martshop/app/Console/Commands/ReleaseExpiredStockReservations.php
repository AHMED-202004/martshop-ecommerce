<?php

namespace App\Console\Commands;

use App\Services\StockReservationService;
use Illuminate\Console\Command;

class ReleaseExpiredStockReservations extends Command
{
    protected $signature = 'reservations:release-expired {--batch=100 : Maximum merchant orders per run}';

    protected $description = 'Release stock held by expired merchant-order confirmations';

    public function handle(StockReservationService $reservations): int
    {
        $batch = filter_var($this->option('batch'), FILTER_VALIDATE_INT);
        if ($batch === false || $batch < 1 || $batch > 1000) {
            $this->error('Batch must be between 1 and 1000.');

            return self::FAILURE;
        }

        $released = $reservations->releaseExpired($batch);
        $this->info("Released {$released} expired stock reservation(s).");

        return self::SUCCESS;
    }
}

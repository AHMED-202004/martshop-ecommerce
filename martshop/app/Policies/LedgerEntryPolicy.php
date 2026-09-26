<?php

namespace App\Policies;

use App\Models\LedgerEntry;
use App\Models\User;

class LedgerEntryPolicy
{
    public function view(User $user, LedgerEntry $entry): bool
    {
        return $user->merchant?->id === $entry->merchant_id
            || $user->hasPermission('ledger.view');
    }
}

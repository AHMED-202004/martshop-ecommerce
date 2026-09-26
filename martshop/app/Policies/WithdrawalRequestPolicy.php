<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WithdrawalRequest;

class WithdrawalRequestPolicy
{
    public function view(User $user, WithdrawalRequest $withdrawal): bool
    {
        return $user->merchant?->id === $withdrawal->merchant_id
            || $user->hasPermission('withdrawals.approve');
    }

    public function review(User $user, WithdrawalRequest $withdrawal): bool
    {
        return $user->hasPermission('withdrawals.approve');
    }
}

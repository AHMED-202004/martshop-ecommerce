<?php

namespace App\Policies;

use App\Models\MerchantPayoutMethod;
use App\Models\User;

class MerchantPayoutMethodPolicy
{
    public function view(User $user, MerchantPayoutMethod $method): bool
    {
        return $user->merchant?->id === $method->merchant_id
            || $user->hasPermission('withdrawals.approve');
    }

    public function review(User $user, MerchantPayoutMethod $method): bool
    {
        return $user->hasPermission('withdrawals.approve');
    }
}

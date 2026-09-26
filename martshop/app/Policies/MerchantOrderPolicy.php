<?php

namespace App\Policies;

use App\Enums\MerchantOrderStatus;
use App\Enums\MerchantVerificationStatus;
use App\Models\MerchantOrder;
use App\Models\User;

class MerchantOrderPolicy
{
    public function view(User $user, MerchantOrder $merchantOrder): bool
    {
        return $user->merchant?->id === $merchantOrder->merchant_id
            || $user->hasPermission('orders.manage');
    }

    public function confirm(User $user, MerchantOrder $merchantOrder): bool
    {
        return $this->manage($user, $merchantOrder)
            && $merchantOrder->status === MerchantOrderStatus::PendingConfirmation;
    }

    public function reject(User $user, MerchantOrder $merchantOrder): bool
    {
        return $this->confirm($user, $merchantOrder);
    }

    public function manage(User $user, MerchantOrder $merchantOrder): bool
    {
        return $this->verifiedOwner($user, $merchantOrder);
    }

    private function verifiedOwner(User $user, MerchantOrder $merchantOrder): bool
    {
        return $user->merchant?->id === $merchantOrder->merchant_id
            && $user->merchant->verification_status === MerchantVerificationStatus::Verified;
    }
}

<?php

namespace App\Policies;

use App\Enums\MerchantVerificationStatus;
use App\Models\Merchant;
use App\Models\User;

class MerchantPolicy
{
    public function view(User $user, Merchant $merchant): bool
    {
        return $merchant->user_id === $user->id || $user->hasPermission('merchants.view');
    }

    public function update(User $user, Merchant $merchant): bool
    {
        if ($merchant->user_id !== $user->id) {
            return false;
        }

        return in_array($merchant->verification_status, [
            MerchantVerificationStatus::Incomplete,
            MerchantVerificationStatus::ChangesRequested,
            MerchantVerificationStatus::Rejected,
        ], true);
    }

    public function submit(User $user, Merchant $merchant): bool
    {
        return $this->update($user, $merchant);
    }

    public function verify(User $user, Merchant $merchant): bool
    {
        return $merchant->user_id !== $user->id && $user->hasPermission('merchants.verify');
    }
}

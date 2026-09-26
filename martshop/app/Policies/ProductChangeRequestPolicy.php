<?php

namespace App\Policies;

use App\Enums\MerchantVerificationStatus;
use App\Enums\ProductChangeRequestStatus;
use App\Models\ProductChangeRequest;
use App\Models\User;

class ProductChangeRequestPolicy
{
    public function view(User $user, ProductChangeRequest $changeRequest): bool
    {
        return $user->merchant?->id === $changeRequest->merchant_id
            || $user->hasPermission('products.moderate');
    }

    public function update(User $user, ProductChangeRequest $changeRequest): bool
    {
        return $user->merchant?->id === $changeRequest->merchant_id
            && $user->merchant->verification_status === MerchantVerificationStatus::Verified
            && $changeRequest->status === ProductChangeRequestStatus::ChangesRequested;
    }

    public function moderate(User $user, ProductChangeRequest $changeRequest): bool
    {
        return $user->hasPermission('products.moderate')
            && $user->merchant?->id !== $changeRequest->merchant_id;
    }
}

<?php

namespace App\Policies;

use App\Enums\MerchantVerificationStatus;
use App\Enums\ProductOfferStatus;
use App\Models\ProductOffer;
use App\Models\User;

class ProductOfferPolicy
{
    public function view(User $user, ProductOffer $offer): bool
    {
        return $offer->merchant?->user_id === $user->id
            || $user->hasPermission('products.moderate');
    }

    public function moderate(User $user, ProductOffer $offer): bool
    {
        return $user->hasPermission('products.moderate')
            && $user->merchant?->id !== $offer->merchant_id;
    }

    public function update(User $user, ProductOffer $offer): bool
    {
        return $this->ownedByVerifiedMerchant($user, $offer)
            && in_array($offer->status, [
                ProductOfferStatus::Active,
                ProductOfferStatus::PendingReview,
                ProductOfferStatus::ChangesRequested,
                ProductOfferStatus::Paused,
            ], true);
    }

    public function pause(User $user, ProductOffer $offer): bool
    {
        return $this->ownedByVerifiedMerchant($user, $offer)
            && $offer->status === ProductOfferStatus::Active;
    }

    public function resume(User $user, ProductOffer $offer): bool
    {
        return $this->ownedByVerifiedMerchant($user, $offer)
            && $offer->status === ProductOfferStatus::Paused
            && $offer->paused_by === $user->id
            && $offer->pause_reason === 'merchant_requested';
    }

    private function ownedByVerifiedMerchant(User $user, ProductOffer $offer): bool
    {
        return $user->merchant?->id === $offer->merchant_id
            && $user->merchant->verification_status === MerchantVerificationStatus::Verified;
    }
}

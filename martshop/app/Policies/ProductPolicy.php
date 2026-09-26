<?php

namespace App\Policies;

use App\Enums\MerchantVerificationStatus;
use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function createOffer(User $user): bool
    {
        return $user->merchant?->verification_status === MerchantVerificationStatus::Verified;
    }

    public function moderate(User $user, Product $product): bool
    {
        if (! $user->hasPermission('products.moderate')) {
            return false;
        }

        $merchantId = $user->merchant?->id;

        return ! $merchantId || (
            $product->created_by_merchant_id !== $merchantId
            && ! $product->offers()->where('merchant_id', $merchantId)->exists()
        );
    }

    public function updateSubmission(User $user, Product $product): bool
    {
        return $user->merchant?->id === $product->created_by_merchant_id
            && $user->merchant->verification_status === MerchantVerificationStatus::Verified
            && $product->status === \App\Enums\ProductStatus::ChangesRequested;
    }

    public function viewSubmissionImage(User $user, Product $product): bool
    {
        return $user->merchant?->id === $product->created_by_merchant_id
            || $this->moderate($user, $product);
    }

    public function proposeChanges(User $user, Product $product): bool
    {
        $merchant = $user->merchant;

        return $merchant?->verification_status === MerchantVerificationStatus::Verified
            && $product->status === \App\Enums\ProductStatus::Active
            && $product->offers()->where('merchant_id', $merchant->id)->exists();
    }
}

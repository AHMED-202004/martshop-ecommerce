<?php

namespace App\Policies;

use App\Models\{RefundDestination, User};

class RefundDestinationPolicy
{
    public function view(User $user, RefundDestination $destination): bool
    {
        return $user->hasPermission('refund-destinations.review')
            || RefundDestination::query()->whereKey($destination->id)
                ->whereHas('refund.dispute.delivery.order', fn ($query) => $query->where('user_id', $user->id))
                ->exists();
    }
}

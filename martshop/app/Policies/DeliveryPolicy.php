<?php

namespace App\Policies;

use App\Models\Delivery;
use App\Models\User;

class DeliveryPolicy
{
    public function view(User $user, Delivery $delivery): bool
    {
        return $user->hasPermission('deliveries.manage')
            || ($delivery->delivery_worker_id === $user->id && $user->hasPermission('deliveries.view-own'));
    }

    public function accept(User $user, Delivery $delivery): bool
    {
        return $delivery->delivery_worker_id === $user->id
            && $user->hasPermission('deliveries.accept');
    }

    public function updateStatus(User $user, Delivery $delivery): bool
    {
        return $delivery->delivery_worker_id === $user->id
            && $user->hasPermission('deliveries.update-status');
    }

    public function confirm(User $user, Delivery $delivery): bool
    {
        return $delivery->delivery_worker_id === $user->id
            && $user->hasPermission('deliveries.confirm');
    }
}

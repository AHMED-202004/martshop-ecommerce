<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function view(User $user, Payment $payment): bool
    {
        return $payment->order?->user_id === $user->id
            || $user->hasPermission('payments.verify');
    }

    public function review(User $user, Payment $payment): bool
    {
        return $user->hasPermission('payments.verify');
    }
}

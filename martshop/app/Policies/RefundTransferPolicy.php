<?php

namespace App\Policies;

use App\Models\{RefundTransfer, User};

class RefundTransferPolicy
{
    public function view(User $user, RefundTransfer $transfer): bool
    {
        return $user->hasPermission('refunds.pay')
            || $transfer->refund->dispute->delivery->order->user_id === $user->id;
    }
}

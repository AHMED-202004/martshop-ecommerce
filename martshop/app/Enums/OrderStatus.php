<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case PendingConfirmation = 'pending_confirmation';
    case PartiallyConfirmed = 'partially_confirmed';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}

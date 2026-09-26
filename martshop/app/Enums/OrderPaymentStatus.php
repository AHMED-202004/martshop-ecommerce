<?php

namespace App\Enums;

enum OrderPaymentStatus: string
{
    case Unpaid = 'unpaid';
    case PendingReview = 'pending_review';
    case Paid = 'paid';
    case ActionRequired = 'action_required';
}

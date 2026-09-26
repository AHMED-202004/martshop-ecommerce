<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case ShortAmount = 'short_amount';
    case Overpaid = 'overpaid';
    case Duplicate = 'duplicate';
    case Suspicious = 'suspicious';
}

<?php

namespace App\Enums;

enum MerchantPayoutMethodStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Disabled = 'disabled';
}

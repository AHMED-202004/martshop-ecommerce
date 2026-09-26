<?php

namespace App\Enums;

enum MerchantVerificationStatus: string
{
    case Incomplete = 'incomplete';
    case PendingReview = 'pending_review';
    case ChangesRequested = 'changes_requested';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
}

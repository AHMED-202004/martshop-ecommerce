<?php

namespace App\Enums;

enum ProductChangeRequestStatus: string
{
    case PendingReview = 'pending_review';
    case ChangesRequested = 'changes_requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}

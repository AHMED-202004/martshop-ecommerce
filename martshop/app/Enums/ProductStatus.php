<?php

namespace App\Enums;

enum ProductStatus: string
{
    case PendingReview = 'pending_review';
    case ChangesRequested = 'changes_requested';
    case Active = 'active';
    case Rejected = 'rejected';
    case Hidden = 'hidden';
}

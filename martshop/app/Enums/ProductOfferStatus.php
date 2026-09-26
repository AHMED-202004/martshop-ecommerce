<?php

namespace App\Enums;

enum ProductOfferStatus: string
{
    case PendingReview = 'pending_review';
    case ChangesRequested = 'changes_requested';
    case Active = 'active';
    case Rejected = 'rejected';
    case Paused = 'paused';
}

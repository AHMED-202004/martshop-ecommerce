<?php

namespace App\Enums;

enum MerchantDocumentStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}

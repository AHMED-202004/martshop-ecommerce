<?php

namespace App\Enums;

enum WithdrawalStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Paid = 'paid';
}

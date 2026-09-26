<?php

namespace App\Enums;

enum LedgerStatus: string
{
    case Pending = 'pending';
    case Available = 'available';
    case Held = 'held';
    case Withdrawn = 'withdrawn';
    case Refunded = 'refunded';
}

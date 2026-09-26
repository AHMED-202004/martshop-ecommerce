<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case Unassigned = 'unassigned';
    case Assigned = 'assigned';
    case Accepted = 'accepted';
    case PickedUp = 'picked_up';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Returned = 'returned';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Delivered, self::Failed, self::Returned, self::Cancelled], true);
    }
}

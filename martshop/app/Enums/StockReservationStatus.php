<?php

namespace App\Enums;

enum StockReservationStatus: string
{
    case Reserved = 'reserved';
    case Consumed = 'consumed';
    case Released = 'released';
    case Expired = 'expired';
}

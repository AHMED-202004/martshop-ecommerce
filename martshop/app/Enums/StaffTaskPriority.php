<?php

namespace App\Enums;

enum StaffTaskPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'منخفضة',
            self::Normal => 'عادية',
            self::High => 'عالية',
            self::Urgent => 'عاجلة',
        };
    }
}

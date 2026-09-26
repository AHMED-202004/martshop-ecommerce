<?php

namespace App\Enums;

enum StaffTaskStatus: string
{
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Waiting = 'waiting';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Assigned => 'مسندة',
            self::InProgress => 'قيد التنفيذ',
            self::Waiting => 'بانتظار متابعة',
            self::Completed => 'مكتملة',
            self::Cancelled => 'ملغاة',
        };
    }
}

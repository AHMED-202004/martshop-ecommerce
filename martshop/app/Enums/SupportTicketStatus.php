<?php

namespace App\Enums;

enum SupportTicketStatus: string
{
    case Open = 'open';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case WaitingCustomer = 'waiting_customer';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'مفتوحة',
            self::Assigned => 'مسندة',
            self::InProgress => 'قيد العمل',
            self::WaitingCustomer => 'بانتظار العميل',
            self::Resolved => 'محلولة',
            self::Closed => 'مغلقة',
        };
    }
}

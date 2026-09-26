<?php

namespace App\Enums;

enum RefundStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Processing = 'processing';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'بانتظار مراجعة الاسترداد',
            self::Approved => 'موافق عليه — لم يُحوّل المبلغ',
            self::Rejected => 'طلب الاسترداد مرفوض',
            self::Processing => 'قيد التحويل اليدوي — لم يُسجّل الإثبات بعد',
            self::Paid => 'سُجّل الاسترداد المالي وإثباته',
        };
    }
}

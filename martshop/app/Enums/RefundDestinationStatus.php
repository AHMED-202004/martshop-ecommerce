<?php

namespace App\Enums;

enum RefundDestinationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'بانتظار التحقق من وسيلة الاستلام',
            self::Verified => 'وسيلة موثّقة — لا يعني تحويل المبلغ',
            self::Rejected => 'وسيلة مرفوضة؛ يمكنك تسجيل بديل',
            self::Revoked => 'وسيلة ملغاة؛ لا تُستخدم للتحويل',
        };
    }
}

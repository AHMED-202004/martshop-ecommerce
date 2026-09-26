<?php

namespace App\Enums;

enum AccountStatus: string
{
    case Invited = 'invited';
    case Active = 'active';
    case OnLeave = 'on_leave';
    case Suspended = 'suspended';
    case Terminated = 'terminated';

    public function label(): string
    {
        return match ($this) {
            self::Invited => 'بانتظار الدعوة',
            self::Active => 'نشط',
            self::OnLeave => 'في إجازة',
            self::Suspended => 'معلّق',
            self::Terminated => 'منتهية خدمته',
        };
    }

    public function canAuthenticate(): bool
    {
        return $this === self::Active;
    }
}

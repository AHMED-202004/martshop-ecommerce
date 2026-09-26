<?php
namespace App\Enums;
enum DeliveryWorkerAvailability: string
{
    case Available = 'available'; case Busy = 'busy'; case Break = 'break'; case Offline = 'offline'; case Suspended = 'suspended';
    public function acceptsAssignments(): bool { return in_array($this, [self::Available, self::Busy], true); }
    public function label(): string { return match ($this) { self::Available => 'متاح', self::Busy => 'مشغول', self::Break => 'استراحة', self::Offline => 'غير متصل', self::Suspended => 'موقوف' }; }
}

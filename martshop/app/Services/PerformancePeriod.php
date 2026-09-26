<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class PerformancePeriod
{
    /** @return array{0: Carbon, 1: Carbon} */
    public function resolve(string $period, ?string $from = null, ?string $to = null): array
    {
        [$start, $end] = match ($period) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            'week' => [now()->startOfWeek(), now()->endOfDay()],
            'month' => [now()->startOfMonth(), now()->endOfDay()],
            'custom' => $this->custom($from, $to),
            default => throw ValidationException::withMessages(['performance_period' => 'فترة الأداء غير مدعومة.']),
        };
        return [$start, $end];
    }

    private function custom(?string $from, ?string $to): array
    {
        if (! $from || ! $to) throw ValidationException::withMessages(['performance_from' => 'حددا تاريخي بداية ونهاية للفترة المخصصة.']);
        $start = Carbon::createFromFormat('Y-m-d', $from)->startOfDay();
        $end = Carbon::createFromFormat('Y-m-d', $to)->endOfDay();
        if ($end->lt($start) || $start->diffInDays($end) > 366) throw ValidationException::withMessages(['performance_to' => 'يجب أن تكون النهاية بعد البداية وألا تتجاوز الفترة 366 يومًا.']);
        return [$start, $end];
    }
}

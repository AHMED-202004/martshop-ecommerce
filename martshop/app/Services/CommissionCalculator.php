<?php

namespace App\Services;

use App\Models\CommissionRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class CommissionCalculator
{
    private array $resolved = [];

    public function __construct(
        private readonly MarketplaceSettings $settings,
    ) {}

    public function calculate(Collection $items): array
    {
        $lines = [];
        $total = 0;

        foreach ($items as $item) {
            if ($item['merchant_id'] === null) {
                continue;
            }

            $rule = $this->resolve((int) $item['merchant_id'], $item['category_id']);
            $percentage = $rule?->percentage ?? $this->settings->string('checkout.commission_percent');
            $basisPoints = $this->settings->decimalToMinorUnits($percentage);
            $commission = intdiv(($item['line_total_minor'] * $basisPoints) + 5000, 10000);
            $commission = min($item['line_total_minor'], max(0, $commission));
            $total += $commission;

            $lines[] = [
                'product_id' => $item['product_id'],
                'offer_id' => $item['offer_id'],
                'category_id' => $item['category_id'],
                'base_amount' => $this->settings->minorUnitsToDecimal($item['line_total_minor']),
                'percentage' => number_format((float) $percentage, 2, '.', ''),
                'commission_amount' => $this->settings->minorUnitsToDecimal($commission),
                'rule_id' => $rule?->id,
                'rule_name' => $rule?->name,
                'source' => $rule ? 'commission_rule' : 'marketplace_setting_fallback',
            ];
        }

        return [
            'total_minor' => $total,
            'snapshot' => [
                'policy' => 'rule_resolution_v1',
                'amount' => $this->settings->minorUnitsToDecimal($total),
                'lines' => $lines,
                'calculated_at' => now()->toIso8601String(),
            ],
        ];
    }

    private function resolve(int $merchantId, ?int $categoryId): ?CommissionRule
    {
        $cacheKey = $merchantId.':'.($categoryId ?? 'none');
        if (array_key_exists($cacheKey, $this->resolved)) {
            return $this->resolved[$cacheKey];
        }
        if (! Schema::hasTable('commission_rules')) {
            return $this->resolved[$cacheKey] = null;
        }

        return $this->resolved[$cacheKey] = CommissionRule::query()
            ->effective()
            ->where(fn ($query) => $query->whereNull('merchant_id')->orWhere('merchant_id', $merchantId))
            ->where(function ($query) use ($categoryId) {
                $query->whereNull('category_id');
                if ($categoryId !== null) {
                    $query->orWhere('category_id', $categoryId);
                }
            })
            ->orderByRaw('(CASE WHEN merchant_id IS NULL THEN 0 ELSE 1 END + CASE WHEN category_id IS NULL THEN 0 ELSE 1 END) DESC')
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->first();
    }
}

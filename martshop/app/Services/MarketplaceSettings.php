<?php

namespace App\Services;

use App\Models\MarketplaceSetting;
use Illuminate\Support\Facades\Schema;

class MarketplaceSettings
{
    private array $cache = [];
    private bool $loaded = false;

    private const DEFAULTS = [
        'settlement.dispute_days' => '7',
        'settlement.auto_release_enabled' => '0',
        'checkout.shipping.flat_fee' => '20.00',
        'checkout.shipping.free_threshold' => '250.00',
        'checkout.reservation_minutes' => '30',
        'checkout.commission_percent' => '0.00',
        'withdrawals.enabled' => '0',
        'withdrawals.minimum_amount' => '50.00',
        'withdrawals.maximum_amount' => '5000.00',
        'withdrawals.daily_limit' => '5000.00',
        'withdrawals.weekly_limit' => '15000.00',
        'support.first_response_sla_minutes' => '240',
        'site.name' => 'Mart.ps',
        'site.contact_phone' => '',
        'site.whatsapp' => '',
        'site.email' => '',
        'site.support_hours' => '',
        'site.emergency_notice' => '',
        'site.chat_enabled' => '1',
        'site.registration_enabled' => '1',
        'site.merchant_registration_enabled' => '1',
        'site.orders_enabled' => '1',
        'site.default_delivery_text' => '',
    ];

    public function string(string $key): string
    {
        $this->load();

        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        return $this->cache[$key] = self::DEFAULTS[$key] ?? '';
    }

    public function integer(string $key): int
    {
        return (int) $this->string($key);
    }

    public function boolean(string $key): bool
    {
        return filter_var($this->string($key), FILTER_VALIDATE_BOOLEAN);
    }

    public function moneyInMinorUnits(string $key): int
    {
        return $this->decimalToMinorUnits($this->string($key));
    }

    public function decimalToMinorUnits(string|int|float|null $value): int
    {
        $normalized = number_format((float) $value, 2, '.', '');
        $sign = str_starts_with($normalized, '-') ? -1 : 1;
        $normalized = ltrim($normalized, '-');
        [$whole, $fraction] = explode('.', $normalized);

        return $sign * (((int) $whole * 100) + (int) $fraction);
    }

    public function minorUnitsToDecimal(int $value): string
    {
        return number_format($value / 100, 2, '.', '');
    }

    public function shippingForSubtotal(int $subtotal): int
    {
        return $subtotal >= $this->moneyInMinorUnits('checkout.shipping.free_threshold')
            ? 0
            : $this->moneyInMinorUnits('checkout.shipping.flat_fee');
    }

    public function deliveryText(): string
    {
        $configured = trim($this->string('site.default_delivery_text'));
        if ($configured !== '') {
            return $configured;
        }

        $fee = $this->moneyInMinorUnits('checkout.shipping.flat_fee');
        $threshold = $this->moneyInMinorUnits('checkout.shipping.free_threshold');
        if ($fee <= 0 || $threshold <= 0) {
            return 'التوصيل مجاني وفق الإعدادات الحالية. تُحسب الرسوم النهائية عند إتمام الطلب.';
        }

        return sprintf(
            'رسوم التوصيل الأساسية ₪%s، ومجانية للطلبات من ₪%s. تُحسب الرسوم النهائية عند إتمام الطلب.',
            $this->minorUnitsToDecimal($fee),
            $this->minorUnitsToDecimal($threshold),
        );
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;
        if (! Schema::hasTable('marketplace_settings')) {
            return;
        }

        $this->cache = MarketplaceSetting::query()
            ->pluck('value', 'key')
            ->map(fn ($value) => (string) $value)
            ->all();
    }
}

<?php

namespace App\Services;

use App\Models\MarketplaceSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MarketplaceSettingsManager
{
    public const DEFINITIONS = [
        'settlement.dispute_days' => ['integer', 'settlement', 'مهلة النزاع بالأيام للطلبات المسلّمة لاحقًا'],
        'settlement.auto_release_enabled' => ['boolean', 'settlement', 'تفعيل التحرير التلقائي بعد مهلة النزاع'],
        'support.first_response_sla_minutes' => ['integer', 'support', 'مهلة أول استجابة لتذاكر الدعم بالدقائق'],
        'site.name' => ['string', 'site', 'اسم الموقع'],
        'site.contact_phone' => ['string', 'site', 'هاتف التواصل'],
        'site.whatsapp' => ['string', 'site', 'رقم واتساب'],
        'site.email' => ['string', 'site', 'بريد التواصل'],
        'site.support_hours' => ['string', 'site', 'ساعات الدعم'],
        'site.emergency_notice' => ['string', 'emergency', 'التنبيه العام'],
        'site.chat_enabled' => ['boolean', 'features', 'إظهار نافذة الدعم'],
        'site.registration_enabled' => ['boolean', 'features', 'تفعيل تسجيل العملاء'],
        'site.merchant_registration_enabled' => ['boolean', 'features', 'تفعيل تسجيل التجار'],
        'site.orders_enabled' => ['boolean', 'features', 'تفعيل إنشاء الطلبات'],
        'site.default_delivery_text' => ['string', 'delivery', 'نص التوصيل الافتراضي'],
        'checkout.shipping.flat_fee' => ['decimal', 'checkout', 'رسوم التوصيل الثابتة'],
        'checkout.shipping.free_threshold' => ['decimal', 'checkout', 'حد التوصيل المجاني'],
        'checkout.reservation_minutes' => ['integer', 'checkout', 'مدة حجز المخزون بالدقائق'],
    ];

    public function __construct(
        private readonly MarketplaceSettings $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function values(): array
    {
        return collect(self::DEFINITIONS)->mapWithKeys(
            fn ($definition, $key) => [$key => $this->settings->string($key)]
        )->all();
    }

    public function update(array $input, User $actor): void
    {
        abort_unless($actor->hasPermission('settings.manage'), 403);

        DB::transaction(function () use ($input, $actor) {
            $before = $this->values();
            $after = [];
            foreach (self::DEFINITIONS as $key => [$type, $group, $label]) {
                if (! array_key_exists($key, $input)) {
                    $after[$key] = $before[$key];
                    continue;
                }
                $value = $this->normalize($type, $input[$key]);
                MarketplaceSetting::query()->updateOrCreate(['key' => $key], [
                    'value' => $value,
                    'type' => $type,
                    'group' => $group,
                    'description' => $label,
                    'updated_by' => $actor->id,
                ]);
                $after[$key] = $value;
            }
            $this->audit->record('settings.updated', null, $before, $after, $input['reason'] ?? null, [
                'changed_keys' => collect($after)->filter(fn ($value, $key) => $before[$key] !== $value)->keys()->values()->all(),
            ]);
        }, 3);
    }

    private function normalize(string $type, mixed $value): string
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            'integer' => (string) ((int) $value),
            'decimal' => number_format((float) $value, 2, '.', ''),
            default => trim((string) $value),
        };
    }
}

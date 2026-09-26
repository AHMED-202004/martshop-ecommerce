<?php

namespace App\Services;

use App\Models\MarketplaceSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class WithdrawalPolicyService
{
    private const KEYS = [
        'enabled' => 'withdrawals.enabled',
        'minimum_amount' => 'withdrawals.minimum_amount',
        'maximum_amount' => 'withdrawals.maximum_amount',
        'daily_limit' => 'withdrawals.daily_limit',
        'weekly_limit' => 'withdrawals.weekly_limit',
    ];

    public function __construct(
        private readonly MarketplaceSettings $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function policy(): array
    {
        return [
            'enabled' => $this->settings->boolean(self::KEYS['enabled']),
            'minimum_minor' => $this->settings->moneyInMinorUnits(self::KEYS['minimum_amount']),
            'maximum_minor' => $this->settings->moneyInMinorUnits(self::KEYS['maximum_amount']),
            'daily_limit_minor' => $this->settings->moneyInMinorUnits(self::KEYS['daily_limit']),
            'weekly_limit_minor' => $this->settings->moneyInMinorUnits(self::KEYS['weekly_limit']),
            'minimum_amount' => $this->settings->string(self::KEYS['minimum_amount']),
            'maximum_amount' => $this->settings->string(self::KEYS['maximum_amount']),
            'daily_limit' => $this->settings->string(self::KEYS['daily_limit']),
            'weekly_limit' => $this->settings->string(self::KEYS['weekly_limit']),
        ];
    }

    public function update(array $data, User $actor): array
    {
        return DB::transaction(function () use ($data, $actor) {
            $before = $this->policy();
            $values = [
                'enabled' => ($data['enabled'] ?? false) ? '1' : '0',
                'minimum_amount' => number_format((float) $data['minimum_amount'], 2, '.', ''),
                'maximum_amount' => number_format((float) $data['maximum_amount'], 2, '.', ''),
                'daily_limit' => number_format((float) $data['daily_limit'], 2, '.', ''),
                'weekly_limit' => number_format((float) $data['weekly_limit'], 2, '.', ''),
            ];
            foreach ($values as $name => $value) {
                MarketplaceSetting::query()->where('key', self::KEYS[$name])->update([
                    'value' => $value,
                    'updated_by' => $actor->id,
                    'updated_at' => now(),
                ]);
            }
            $after = [
                'enabled' => $values['enabled'] === '1',
                'minimum_amount' => $values['minimum_amount'],
                'maximum_amount' => $values['maximum_amount'],
                'daily_limit' => $values['daily_limit'],
                'weekly_limit' => $values['weekly_limit'],
            ];
            $this->audit->record('withdrawal_policy.updated', null, $before, $after, metadata: [
                'actor_id' => $actor->id,
            ]);

            return $after;
        }, 3);
    }
}

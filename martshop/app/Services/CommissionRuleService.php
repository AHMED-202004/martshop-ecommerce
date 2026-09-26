<?php

namespace App\Services;

use App\Models\CommissionRule;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CommissionRuleService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function create(array $data, User $actor): CommissionRule
    {
        abort_unless($actor->hasPermission('commissions.manage'), 403);

        return DB::transaction(function () use ($data, $actor) {
            $rule = CommissionRule::create($this->payload($data) + [
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $this->audit->record('commission_rule.created', $rule, null, $this->snapshot($rule));

            return $rule;
        });
    }

    public function update(CommissionRule $rule, array $data, User $actor): CommissionRule
    {
        abort_unless($actor->hasPermission('commissions.manage'), 403);

        return DB::transaction(function () use ($rule, $data, $actor) {
            $locked = CommissionRule::query()->whereKey($rule->id)->lockForUpdate()->firstOrFail();
            $before = $this->snapshot($locked);
            $locked->update($this->payload($data) + ['updated_by' => $actor->id]);
            $this->audit->record('commission_rule.updated', $locked, $before, $this->snapshot($locked));

            return $locked->refresh();
        });
    }

    private function payload(array $data): array
    {
        return [
            'name' => trim($data['name']),
            'merchant_id' => $data['merchant_id'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'percentage' => number_format((float) $data['percentage'], 2, '.', ''),
            'priority' => (int) ($data['priority'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
        ];
    }

    private function snapshot(CommissionRule $rule): array
    {
        return $rule->only([
            'name', 'merchant_id', 'category_id', 'percentage', 'priority',
            'is_active', 'starts_at', 'ends_at',
        ]);
    }
}

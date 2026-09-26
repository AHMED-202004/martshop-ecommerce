<?php
namespace App\Services;
use App\Models\DeliverySlaRule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
class DeliverySlaRuleService
{
    public function __construct(private readonly AuditLogger $audit) {}
    public function save(?DeliverySlaRule $rule, array $data, User $actor): DeliverySlaRule
    {
        abort_unless($actor->hasPermission('deliveries.manage'), 403);
        return DB::transaction(function () use ($rule, $data, $actor) {
            $rule = $rule ? DeliverySlaRule::query()->lockForUpdate()->findOrFail($rule->id) : new DeliverySlaRule();
            $before = $rule->exists ? $this->snapshot($rule) : null;
            $rule->fill(collect($data)->except(['reason', 'current_password'])->all());
            if (! $rule->exists) $rule->created_by = $actor->id;
            $rule->save();
            $this->audit->record($before ? 'delivery_sla.updated' : 'delivery_sla.created', $rule, $before, $this->snapshot($rule), $data['reason']);
            return $rule;
        }, 3);
    }
    private function snapshot(DeliverySlaRule $rule): array { return $rule->only(['name', 'origin_location_id', 'destination_area', 'order_type', 'delivery_method', 'minimum_distance_km', 'maximum_distance_km', 'target_minutes', 'priority', 'is_active']); }
}

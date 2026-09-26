<?php

namespace App\Services;

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PaymentMethodService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function create(array $data, User $actor): PaymentMethod
    {
        abort_unless($actor->hasPermission('payment-methods.manage'), 403);

        return DB::transaction(function () use ($data, $actor) {
            $method = PaymentMethod::create($this->payload($data));
            $this->audit->record(
                'payment_method.created',
                $method,
                null,
                $this->auditSnapshot($method),
                metadata: ['actor_id' => $actor->id],
            );

            return $method;
        });
    }

    public function update(PaymentMethod $method, array $data, User $actor): PaymentMethod
    {
        abort_unless($actor->hasPermission('payment-methods.manage'), 403);

        return DB::transaction(function () use ($method, $data, $actor) {
            $locked = PaymentMethod::query()->whereKey($method->id)->lockForUpdate()->firstOrFail();
            $before = $this->auditSnapshot($locked);
            $beforeAccountDetails = $locked->only(['account_name', 'account_identifier', 'instructions']);
            $locked->update($this->payload($data));
            $this->audit->record(
                'payment_method.updated',
                $locked,
                $before,
                $this->auditSnapshot($locked),
                metadata: [
                    'actor_id' => $actor->id,
                    'account_details_changed' => $beforeAccountDetails !== $locked->only(array_keys($beforeAccountDetails)),
                ],
            );

            return $locked->refresh();
        });
    }

    private function payload(array $data): array
    {
        return [
            'name' => trim($data['name']),
            'slug' => mb_strtolower(trim($data['slug'])),
            'type' => 'manual_transfer',
            'is_active' => (bool) ($data['is_active'] ?? false),
            'account_name' => isset($data['account_name']) ? trim($data['account_name']) : null,
            'account_identifier' => isset($data['account_identifier']) ? trim($data['account_identifier']) : null,
            'instructions' => isset($data['instructions']) ? trim($data['instructions']) : null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    private function auditSnapshot(PaymentMethod $method): array
    {
        return $method->only(['name', 'slug', 'type', 'is_active', 'sort_order']);
    }
}

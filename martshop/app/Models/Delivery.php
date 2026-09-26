<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class Delivery extends Model
{
    protected $fillable = [
        'order_id', 'merchant_order_id', 'delivery_worker_id', 'queue_department_id', 'status', 'reference',
        'assignment_key', 'origin_snapshot', 'destination_snapshot', 'assignment_notes',
        'confirmation_pin', 'confirmation_pin_hash', 'assigned_by', 'assigned_at',
        'accepted_at', 'heading_to_merchant_at', 'merchant_arrived_at', 'picked_up_at',
        'out_for_delivery_at', 'customer_arrived_at', 'in_transit_at', 'delivered_at', 'expected_delivery_at',
        'failed_at', 'returned_at', 'cancelled_at', 'outcome_by', 'outcome_reason', 'delivery_note',
        'sla_rule_id', 'order_type', 'delivery_method', 'distance_km',
        'delay_reason', 'delay_responsibility', 'delay_note', 'delay_recorded_by', 'delay_recorded_at',
        'lock_version', 'settlement_hold_days', 'settlement_due_at', 'settled_at',
    ];

    protected $hidden = [
        'confirmation_pin', 'confirmation_pin_hash', 'assignment_key',
        'origin_snapshot', 'destination_snapshot', 'assignment_notes', 'delivery_note',
        'outcome_reason',
        'delay_note',
    ];

    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'origin_snapshot' => 'array',
            'destination_snapshot' => 'array',
            'assigned_at' => 'datetime',
            'accepted_at' => 'datetime',
            'heading_to_merchant_at' => 'datetime',
            'merchant_arrived_at' => 'datetime',
            'confirmation_pin' => 'encrypted',
            'picked_up_at' => 'datetime',
            'out_for_delivery_at' => 'datetime',
            'customer_arrived_at' => 'datetime',
            'in_transit_at' => 'datetime',
            'delivered_at' => 'datetime',
            'expected_delivery_at' => 'datetime',
            'failed_at' => 'datetime',
            'returned_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'distance_km' => 'decimal:2',
            'delay_recorded_at' => 'datetime',
            'lock_version' => 'integer',
            'settlement_hold_days' => 'integer',
            'settlement_due_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Delivery records cannot be deleted.'));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function merchantOrder(): BelongsTo
    {
        return $this->belongsTo(MerchantOrder::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_worker_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function outcomeActor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'outcome_by');
    }

    public function slaRule(): BelongsTo
    {
        return $this->belongsTo(DeliverySlaRule::class, 'sla_rule_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(DeliveryEvent::class);
    }

    public function proof(): HasOne
    {
        return $this->hasOne(DeliveryProof::class);
    }

    public function rating(): HasOne
    {
        return $this->hasOne(DeliveryRating::class);
    }

    public function dispute(): HasOne
    {
        return $this->hasOne(DeliveryDispute::class);
    }
}

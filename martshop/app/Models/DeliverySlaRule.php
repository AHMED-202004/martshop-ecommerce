<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class DeliverySlaRule extends Model
{
    protected $fillable = ['name', 'origin_location_id', 'destination_area', 'order_type', 'delivery_method', 'minimum_distance_km', 'maximum_distance_km', 'target_minutes', 'priority', 'is_active', 'created_by'];
    protected function casts(): array { return ['minimum_distance_km' => 'decimal:2', 'maximum_distance_km' => 'decimal:2', 'target_minutes' => 'integer', 'priority' => 'integer', 'is_active' => 'boolean']; }
    public function originLocation(): BelongsTo { return $this->belongsTo(Location::class, 'origin_location_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}

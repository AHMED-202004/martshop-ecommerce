<?php
namespace App\Models;
use App\Enums\DeliveryWorkerAvailability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class DeliveryWorkerProfile extends Model
{
    protected $fillable = ['user_id', 'availability', 'availability_changed_at', 'availability_changed_by', 'last_activity_at'];
    protected function casts(): array { return ['availability' => DeliveryWorkerAvailability::class, 'availability_changed_at' => 'datetime', 'last_activity_at' => 'datetime']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}

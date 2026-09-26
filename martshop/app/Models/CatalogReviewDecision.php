<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;
class CatalogReviewDecision extends Model
{
    public $timestamps = false;
    protected $fillable = ['subject_type', 'subject_id', 'reviewer_id', 'from_status', 'to_status', 'origin', 'submitted_at', 'decided_at', 'is_reversal'];
    protected function casts(): array { return ['submitted_at' => 'datetime', 'decided_at' => 'datetime', 'is_reversal' => 'boolean']; }
    protected static function booted(): void { static::updating(fn () => throw new LogicException('Catalog review decisions are append-only.')); static::deleting(fn () => throw new LogicException('Catalog review decisions are append-only.')); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewer_id'); }
    public function subject(): MorphTo { return $this->morphTo(); }
}

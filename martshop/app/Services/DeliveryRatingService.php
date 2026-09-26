<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\DeliveryRating;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryRatingService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function create(Delivery $delivery, User $customer, int $rating, ?string $comment): DeliveryRating
    {
        return DB::transaction(function () use ($delivery, $customer, $rating, $comment) {
            $locked = Delivery::query()->with('order:id,user_id')->whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->order->user_id === $customer->id, 403);
            if ($locked->status !== DeliveryStatus::Delivered) {
                throw ValidationException::withMessages(['rating' => 'يمكن تقييم تجربة التوصيل بعد اكتمال التسليم فقط.']);
            }
            if ($locked->rating()->exists()) {
                throw ValidationException::withMessages(['rating' => 'تم تقييم تجربة التوصيل مسبقًا.']);
            }
            $record = DeliveryRating::query()->create([
                'delivery_id' => $locked->id, 'customer_id' => $customer->id,
                'rating' => $rating, 'comment' => $comment, 'created_at' => now(),
            ]);
            $this->audit->record('delivery.rated', $locked, null, [
                'rating' => $rating, 'has_comment' => filled($comment),
            ]);
            return $record;
        }, 3);
    }
}

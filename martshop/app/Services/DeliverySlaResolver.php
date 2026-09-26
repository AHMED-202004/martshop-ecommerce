<?php
namespace App\Services;
use App\Models\DeliverySlaRule;
class DeliverySlaResolver
{
    public function resolve(?int $originLocationId, string $destinationArea, string $orderType, string $deliveryMethod, ?float $distanceKm): ?DeliverySlaRule
    {
        $area = mb_strtolower(trim($destinationArea));
        return DeliverySlaRule::query()->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('origin_location_id')->orWhere('origin_location_id', $originLocationId))
            ->where(fn ($q) => $q->whereNull('destination_area')->orWhereRaw('LOWER(destination_area) = ?', [$area]))
            ->where(fn ($q) => $q->whereNull('order_type')->orWhere('order_type', $orderType))
            ->where(fn ($q) => $q->whereNull('delivery_method')->orWhere('delivery_method', $deliveryMethod))
            ->when($distanceKm === null,
                fn ($q) => $q->whereNull('minimum_distance_km')->whereNull('maximum_distance_km'),
                fn ($q) => $q->where(fn ($d) => $d->whereNull('minimum_distance_km')->orWhere('minimum_distance_km', '<=', $distanceKm))
                    ->where(fn ($d) => $d->whereNull('maximum_distance_km')->orWhere('maximum_distance_km', '>=', $distanceKm)))
            ->orderByDesc('priority')
            ->orderByRaw('(origin_location_id IS NOT NULL) + (destination_area IS NOT NULL) + (order_type IS NOT NULL) + (delivery_method IS NOT NULL) + (minimum_distance_km IS NOT NULL) + (maximum_distance_km IS NOT NULL) DESC')
            ->orderByDesc('id')->first();
    }
}

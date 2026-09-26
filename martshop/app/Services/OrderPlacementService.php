<?php

namespace App\Services;

use App\Enums\MerchantOrderStatus;
use App\Enums\MerchantVerificationStatus;
use App\Enums\OrderStatus;
use App\Enums\StockReservationStatus;
use App\Models\MerchantOrder;
use App\Models\OfferVariant;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderPlacementService
{
    public function __construct(
        private readonly CatalogItemResolver $resolver,
        private readonly MarketplaceSettings $settings,
        private readonly CommissionCalculator $commissions,
        private readonly AuditLogger $audit,
    ) {}

    public function place(User $user, array $cartItems, string $paymentMethod, string $checkoutToken): Order
    {
        if (! Str::isUuid($checkoutToken)) {
            throw ValidationException::withMessages(['checkout_token' => 'رمز تأكيد الطلب غير صالح.']);
        }
        if ($paymentMethod !== 'manual_transfer') {
            throw ValidationException::withMessages(['payment_method' => 'طريقة الدفع المحددة غير متاحة.']);
        }
        if ($existing = $this->existingOrder($user, $checkoutToken)) {
            return $existing;
        }
        if (! $this->settings->boolean('site.orders_enabled')) {
            throw ValidationException::withMessages(['cart' => 'إنشاء الطلبات متوقف مؤقتًا.']);
        }
        if (count($cartItems) > 100) {
            throw ValidationException::withMessages(['cart' => 'تجاوزت السلة الحد الأقصى المسموح به.']);
        }
        $deliveryAddress = $this->deliveryAddressSnapshot($user);

        try {
            return DB::transaction(function () use (
                $user, $cartItems, $paymentMethod, $checkoutToken, $deliveryAddress
            ) {
                if ($existing = Order::query()
                    ->where('user_id', $user->id)
                    ->where('checkout_token', $checkoutToken)
                    ->lockForUpdate()
                    ->first()) {
                    return $existing;
                }

                $prepared = $this->prepareAndReserve($cartItems);
                $subtotal = $prepared->sum('line_total_minor');
                if ($subtotal <= 0) {
                    throw ValidationException::withMessages(['cart' => 'تعذر احتساب قيمة سلة المشتريات.']);
                }

                $currencies = $prepared->pluck('currency')->unique()->values();
                if ($currencies->count() !== 1) {
                    throw ValidationException::withMessages(['cart' => 'لا يمكن جمع عملات مختلفة في طلب واحد.']);
                }

                $shipping = $this->settings->shippingForSubtotal($subtotal);
                $groups = $this->groupItems($prepared, $shipping, $subtotal);
                $hasMerchantConfirmation = $groups->contains(fn (array $group) => $group['merchant_id'] !== null);
                $reservationExpiresAt = $hasMerchantConfirmation
                    ? now()->addMinutes(max(1, $this->settings->integer('checkout.reservation_minutes')))
                    : null;

                $order = Order::create([
                    'user_id' => $user->id,
                    'subtotal' => $this->money($subtotal),
                    'shipping_fee' => $this->money($shipping),
                    'total' => $this->money($subtotal + $shipping),
                    'currency' => $currencies->first(),
                    'delivery_address_snapshot' => $deliveryAddress,
                    'status' => $hasMerchantConfirmation
                        ? OrderStatus::PendingConfirmation
                        : OrderStatus::Confirmed,
                    'payment_method' => $paymentMethod,
                    'reservation_expires_at' => $reservationExpiresAt,
                    'checkout_token' => $checkoutToken,
                ]);

                foreach ($groups as $group) {
                    $isPlatform = $group['merchant_id'] === null;
                    $merchantOrder = MerchantOrder::create([
                        'order_id' => $order->id,
                        'merchant_id' => $group['merchant_id'],
                        'group_key' => $group['group_key'],
                        'status' => $isPlatform
                            ? MerchantOrderStatus::Confirmed
                            : MerchantOrderStatus::PendingConfirmation,
                        'product_subtotal' => $this->money($group['subtotal_minor']),
                        'delivery_fee' => $this->money($group['shipping_minor']),
                        'service_fee' => '0.00',
                        'commission_amount' => $this->money($group['commission_minor']),
                        'total' => $this->money($group['subtotal_minor'] + $group['shipping_minor']),
                        'currency' => $currencies->first(),
                        'origin_location_id' => $group['origin_location_id'],
                        'commission_snapshot' => $group['commission_snapshot'],
                        'delivery_snapshot' => [
                            'policy' => 'legacy_global_threshold',
                            'order_shipping_fee' => $this->money($shipping),
                            'allocated_fee' => $this->money($group['shipping_minor']),
                            'flat_fee' => $this->settings->string('checkout.shipping.flat_fee'),
                            'free_threshold' => $this->settings->string('checkout.shipping.free_threshold'),
                            'origin_location_ids' => $group['location_ids'],
                        ],
                        'confirmed_at' => $isPlatform ? now() : null,
                    ]);

                    foreach ($group['items'] as $item) {
                        $orderItem = OrderItem::create([
                            'order_id' => $order->id,
                            'merchant_order_id' => $merchantOrder->id,
                            'product_id' => $item['product_id'],
                            'product_offer_id' => $item['offer_id'],
                            'merchant_id' => $item['merchant_id'],
                            'product_name' => $item['name'],
                            'price' => $this->money($item['unit_minor']),
                            'qty' => $item['qty'],
                            'image' => $item['image'],
                            'variant_snapshot' => array_merge($item['options'], [
                                'offer_variant_id' => $item['variant_id'],
                                'sku' => $item['variant_sku'],
                                'attributes' => $item['options'],
                            ]),
                            'offer_snapshot' => [
                                'offer_id' => $item['offer_id'],
                                'merchant_id' => $item['merchant_id'],
                                'location_id' => $item['location_id'],
                                'currency' => $item['currency'],
                                'price' => $item['unit_minor'] / 100,
                                'unit_price' => $this->money($item['unit_minor']),
                                'compare_at_price' => $item['compare_at_price'],
                                'merchant_order_delivery_fee' => $this->money($group['shipping_minor']),
                                'merchant_order_commission' => $this->money($group['commission_minor']),
                                'stock_reserved_at' => now()->toIso8601String(),
                            ],
                        ]);

                        StockReservation::create([
                            'order_id' => $order->id,
                            'merchant_order_id' => $merchantOrder->id,
                            'order_item_id' => $orderItem->id,
                            'product_offer_id' => $item['offer_id'],
                            'offer_variant_id' => $item['variant_id'],
                            'quantity' => $item['qty'],
                            'status' => $isPlatform
                                ? StockReservationStatus::Consumed
                                : StockReservationStatus::Reserved,
                            'offer_stock_was_tracked' => $item['offer_stock_tracked'],
                            'variant_stock_was_tracked' => $item['variant_stock_tracked'],
                            'expires_at' => $isPlatform ? null : $reservationExpiresAt,
                            'consumed_at' => $isPlatform ? now() : null,
                        ]);
                    }
                }

                $this->audit->record('order.placed', $order, null, [
                    'status' => $order->status->value,
                    'subtotal' => $order->subtotal,
                    'shipping_fee' => $order->shipping_fee,
                    'total' => $order->total,
                    'merchant_orders' => $groups->count(),
                ]);

                return $order->load(['items', 'merchantOrders', 'reservations']);
            }, 3);
        } catch (QueryException $exception) {
            if ($existing = $this->existingOrder($user, $checkoutToken)) {
                return $existing;
            }

            throw $exception;
        }
    }

    private function prepareAndReserve(array $cartItems): Collection
    {
        if ($cartItems === []) {
            throw ValidationException::withMessages(['cart' => 'السلة فارغة.']);
        }

        $offerIds = collect($cartItems)->pluck('offer_id')->filter()->map(fn ($id) => (int) $id)->unique()->sort()->values();
        $productIds = collect($cartItems)
            ->map(fn (array $item) => $item['product_id'] ?? (is_numeric($item['id'] ?? null) ? $item['id'] : null))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values();

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->publishedForStore()
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $offers = ProductOffer::query()
            ->with('merchant')
            ->whereIn('id', $offerIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $productVariants = ProductVariant::query()
            ->whereIn('product_id', $productIds)
            ->available()
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->groupBy('product_id');

        $variants = OfferVariant::query()
            ->whereIn('product_offer_id', $offerIds)
            ->active()
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->groupBy('product_offer_id');

        return collect($cartItems)->map(function (array $item) use ($products, $offers, $variants, $productVariants) {
            $qty = (int) ($item['qty'] ?? 1);
            if ($qty < 1 || $qty > 999) {
                throw ValidationException::withMessages(['cart' => 'كمية أحد المنتجات غير صالحة.']);
            }

            $productId = isset($item['product_id']) && is_numeric($item['product_id'])
                ? (int) $item['product_id']
                : (is_numeric($item['id'] ?? null) ? (int) $item['id'] : null);
            $offerId = isset($item['offer_id']) && is_numeric($item['offer_id'])
                ? (int) $item['offer_id']
                : null;
            $offerVariantId = isset($item['offer_variant_id']) && is_numeric($item['offer_variant_id'])
                ? (int) $item['offer_variant_id']
                : null;
            $options = $this->normalizedOptions($item['options'] ?? []);

            if ($productId !== null) {
                /** @var Product|null $product */
                $product = $products->get($productId);
                if (! $product) {
                    throw ValidationException::withMessages(['cart' => 'أحد المنتجات لم يعد متاحًا.']);
                }

                $offer = null;
                $variant = null;
                $unitMinor = null;

                if ($offerId !== null) {
                    /** @var ProductOffer|null $offer */
                    $offer = $offers->get($offerId);
                    if (! $this->offerIsSellable($offer, $productId)) {
                        throw ValidationException::withMessages(['cart' => "عرض المنتج {$product->name} لم يعد متاحًا."]);
                    }

                    $offerVariants = $variants->get($offerId, collect());
                    $variant = $this->selectVariant(
                        $offerVariants,
                        $options,
                        $product->name,
                        $offerVariantId,
                    );
                    $unitMinor = $this->settings->decimalToMinorUnits($variant?->price ?? $offer->price);

                    if ($offer->stock !== null && $offer->stock < $qty) {
                        throw ValidationException::withMessages(['cart' => "الكمية المطلوبة من {$product->name} غير متوفرة."]);
                    }
                    if ($variant?->stock !== null && $variant->stock < $qty) {
                        throw ValidationException::withMessages(['cart' => "الكمية المطلوبة من خيار {$product->name} غير متوفرة."]);
                    }

                    if ($offer->stock !== null) {
                        $offer->decrement('stock', $qty);
                    }
                    if ($variant?->stock !== null) {
                        $variant->decrement('stock', $qty);
                    }
                } else {
                    if ($product->source === 'merchant_submission') {
                        throw ValidationException::withMessages(['cart' => "اختر عرضًا متاحًا للمنتج {$product->name}."]);
                    }
                    $this->validateProductVariant(
                        $productVariants->get($product->id, collect()),
                        $options,
                        $product->name,
                    );
                    $unitMinor = $this->settings->decimalToMinorUnits($product->final_price);
                }

                return [
                    'product_id' => $product->id,
                    'category_id' => $product->category_id,
                    'offer_id' => $offer?->id,
                    'variant_id' => $variant?->id,
                    'variant_sku' => $variant?->sku,
                    'merchant_id' => $offer?->merchant_id,
                    'location_id' => $offer?->location_id,
                    'currency' => $offer?->currency ?? 'ILS',
                    'compare_at_price' => $offer?->compare_at_price !== null ? (string) $offer->compare_at_price : null,
                    'name' => $product->name,
                    'image' => $product->image ? asset($product->image) : null,
                    'options' => $variant?->attributes ?? $options,
                    'qty' => $qty,
                    'unit_minor' => $unitMinor,
                    'line_total_minor' => $unitMinor * $qty,
                    'offer_stock_tracked' => $offer?->stock !== null,
                    'variant_stock_tracked' => $variant?->stock !== null,
                ];
            }

            $resolved = $this->resolver->resolve($item['id'] ?? null, $item['slug'] ?? null, null);
            if (! $resolved || $resolved['product_id'] !== null) {
                throw ValidationException::withMessages(['cart' => 'أحد المنتجات لم يعد متاحًا.']);
            }

            $unitMinor = $this->settings->decimalToMinorUnits($resolved['price']);

            return [
                'product_id' => null,
                'category_id' => null,
                'offer_id' => null,
                'variant_id' => null,
                'variant_sku' => null,
                'merchant_id' => null,
                'location_id' => null,
                'currency' => 'ILS',
                'compare_at_price' => $resolved['compare_at_price'],
                'name' => $resolved['name'],
                'image' => $resolved['image'],
                'options' => $options,
                'qty' => $qty,
                'unit_minor' => $unitMinor,
                'line_total_minor' => $unitMinor * $qty,
                'offer_stock_tracked' => false,
                'variant_stock_tracked' => false,
            ];
        })->values();
    }

    private function offerIsSellable(?ProductOffer $offer, int $productId): bool
    {
        if (! $offer || $offer->product_id !== $productId || $offer->status->value !== 'active') {
            return false;
        }
        if ($offer->expires_at?->isPast() || ($offer->stock !== null && $offer->stock < 1)) {
            return false;
        }

        return $offer->merchant_id === null
            || $offer->merchant?->verification_status === MerchantVerificationStatus::Verified;
    }

    private function selectVariant(
        Collection $variants,
        array $options,
        string $productName,
        ?int $variantId = null,
    ): ?OfferVariant
    {
        if ($variantId !== null) {
            /** @var OfferVariant|null $variant */
            $variant = $variants->firstWhere('id', $variantId);
            if (! $variant) {
                throw ValidationException::withMessages(['cart' => "الخيار المحدد للمنتج {$productName} لم يعد متاحًا."]);
            }

            $attributes = $this->normalizedOptions($variant->attributes ?? []);
            if (! collect($options)->every(
                fn (string $value, string $key) => ($attributes[$key] ?? null) === $value
            )) {
                throw ValidationException::withMessages(['cart' => "بيانات خيار المنتج {$productName} غير متطابقة."]);
            }

            return $variant;
        }

        if ($variants->isEmpty()) {
            return null;
        }

        if ($options === [] && $variants->count() === 1) {
            return $variants->first();
        }

        $matches = $variants->filter(function (OfferVariant $variant) use ($options) {
            $attributes = $this->normalizedOptions($variant->attributes ?? []);

            return collect($options)->every(
                fn (string $value, string $key) => ($attributes[$key] ?? null) === $value
            );
        })->values();

        if ($matches->count() !== 1) {
            throw ValidationException::withMessages(['cart' => "الخيار المحدد للمنتج {$productName} لم يعد متاحًا."]);
        }

        return $matches->first();
    }

    private function validateProductVariant(Collection $variants, array $options, string $productName): void
    {
        if ($variants->isEmpty()) {
            if ($options !== []) {
                throw ValidationException::withMessages(['cart' => "الخيار المحدد للمنتج {$productName} غير متاح."]);
            }

            return;
        }

        if ($options === []) {
            if ($variants->count() > 1) {
                throw ValidationException::withMessages(['cart' => "اختر خيارًا متاحًا للمنتج {$productName}."]);
            }

            return;
        }

        $matched = $variants->contains(function (ProductVariant $variant) use ($options) {
            $attributes = $this->normalizedOptions(array_filter([
                'size' => $variant->size_value,
                'color' => $variant->color,
            ], fn ($value) => $value !== null && $value !== ''));

            return collect($options)->every(
                fn (string $value, string $key) => ($attributes[$key] ?? null) === $value
            );
        });

        if (! $matched) {
            throw ValidationException::withMessages(['cart' => "الخيار المحدد للمنتج {$productName} غير متاح."]);
        }
    }

    private function groupItems(Collection $items, int $shipping, int $subtotal): Collection
    {
        $groups = $items
            ->groupBy(fn (array $item) => $item['merchant_id'] ? 'merchant:'.$item['merchant_id'] : 'platform')
            ->sortKeys();
        $remainingShipping = $shipping;
        $lastKey = $groups->keys()->last();

        return $groups->map(function (Collection $groupItems, string $key) use (
            $shipping, $subtotal, &$remainingShipping, $lastKey
        ) {
            $groupSubtotal = $groupItems->sum('line_total_minor');
            $commission = $this->commissions->calculate($groupItems);
            $allocatedShipping = $key === $lastKey
                ? $remainingShipping
                : intdiv($shipping * $groupSubtotal, $subtotal);
            $remainingShipping -= $allocatedShipping;
            $locationIds = $groupItems->pluck('location_id')->filter()->unique()->values()->all();

            return [
                'group_key' => $key,
                'merchant_id' => $groupItems->first()['merchant_id'],
                'subtotal_minor' => $groupSubtotal,
                'shipping_minor' => $allocatedShipping,
                'commission_minor' => $commission['total_minor'],
                'commission_snapshot' => $commission['snapshot'],
                'origin_location_id' => count($locationIds) === 1 ? $locationIds[0] : null,
                'location_ids' => $locationIds,
                'items' => $groupItems->all(),
            ];
        })->values();
    }

    private function normalizedOptions(array $options): array
    {
        $normalized = [];
        foreach ($options as $key => $value) {
            if ($value === null || trim((string) $value) === '') {
                continue;
            }
            $normalized[mb_strtolower(trim((string) $key))] = mb_strtolower(trim((string) $value));
        }
        ksort($normalized);

        return $normalized;
    }

    private function money(int $minorUnits): string
    {
        return $this->settings->minorUnitsToDecimal($minorUnits);
    }

    private function existingOrder(User $user, string $checkoutToken): ?Order
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->where('checkout_token', $checkoutToken)
            ->first();
    }

    private function deliveryAddressSnapshot(User $user): array
    {
        $snapshot = [
            'recipient_name' => trim(($user->first_name ?? '').' '.($user->last_name ?? ''))
                ?: trim((string) $user->name),
            'governorate' => trim((string) $user->governorate),
            'city' => trim((string) $user->city),
            'address' => trim((string) $user->address),
            'mobile' => trim((string) ($user->mobile ?: $user->phone)),
            'alt_mobile' => trim((string) $user->alt_mobile) ?: null,
            'source' => 'checkout_user_profile',
            'captured_at' => now()->toIso8601String(),
        ];
        foreach (['recipient_name', 'governorate', 'city', 'address', 'mobile'] as $field) {
            if ($snapshot[$field] === '') {
                throw ValidationException::withMessages([
                    'address' => 'أكمل اسم المستلم والعنوان والمدينة ورقم الجوال قبل تأكيد الطلب.',
                ]);
            }
        }

        return $snapshot;
    }
}

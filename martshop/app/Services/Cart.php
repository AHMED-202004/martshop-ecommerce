<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class Cart
{
    const KEY = 'cart.items';
    const MAX_LINES = 100;
    const MAX_QUANTITY = 999;

    // جلب العناصر
    public static function items(): array
    {
        return session(self::KEY, []);
    }

    // عدّاد العناصر (مجموع الكميات)
    public static function count(): int
    {
        return array_sum(array_column(self::items(), 'qty'));
    }

    // إجمالي السعر
    public static function total(): float
    {
        return array_reduce(self::items(), function ($sum, $item) {
            return $sum + ($item['price'] * $item['qty']);
        }, 0.0);
    }

    // توليد مُعرّف الصف (rowId) لتمييز المنتج + خياراته
    public static function makeRowId(
        $id,
        array $options = [],
        mixed $offerId = null,
        mixed $offerVariantId = null,
    ): string
    {
        ksort($options);
        return md5($id.'|'.($offerId ?? 'no-offer').'|'.($offerVariantId ?? 'no-variant').'|'.json_encode($options));
    }

    // إضافة عنصر
    public static function add(array $data): void
    {
        $items = self::items();

        $rowId = self::makeRowId(
            $data['id'],
            $data['options'] ?? [],
            $data['offer_id'] ?? null,
            $data['offer_variant_id'] ?? null,
        );
        if (isset($items[$rowId])) {
            $quantity = (int) ($items[$rowId]['qty'] ?? 0) + max(1, (int) ($data['qty'] ?? 1));
            if ($quantity > self::MAX_QUANTITY) {
                throw ValidationException::withMessages(['qty' => 'الكمية القصوى للمنتج هي 999.']);
            }
            $items[$rowId]['qty'] = $quantity;
        } else {
            if (count($items) >= self::MAX_LINES) {
                throw ValidationException::withMessages(['cart' => 'تجاوزت السلة الحد الأقصى المسموح به.']);
            }
            $items[$rowId] = [
                'rowId'  => $rowId,
                'id'     => $data['id'],           // id أو slug
                'product_id' => $data['product_id'] ?? null,
                'offer_id' => $data['offer_id'] ?? null,
                'offer_variant_id' => $data['offer_variant_id'] ?? null,
                'merchant_id' => $data['merchant_id'] ?? null,
                'location_id' => $data['location_id'] ?? null,
                'currency' => $data['currency'] ?? 'ILS',
                'compare_at_price' => $data['compare_at_price'] ?? null,
                'name'   => $data['name'],
                'price'  => (float)$data['price'],
                'qty'    => max(1, (int)($data['qty'] ?? 1)),
                'image'  => $data['image'] ?? null,
                'slug'   => $data['slug']  ?? null,
                'options'=> $data['options'] ?? [],   // مثل: size, color
            ];
        }
        session([self::KEY => $items]);
    }

    // تحديث الكمية
    public static function update(string $rowId, int $qty): void
    {
        if ($qty < 1 || $qty > self::MAX_QUANTITY) {
            throw ValidationException::withMessages(['qty' => 'الكمية المطلوبة غير صالحة.']);
        }
        $items = self::items();
        if (isset($items[$rowId])) {
            $items[$rowId]['qty'] = $qty;
            session([self::KEY => $items]);
        }
    }

    // حذف عنصر
    public static function remove(string $rowId): void
    {
        $items = self::items();
        unset($items[$rowId]);
        session([self::KEY => $items]);
    }

    // تفريغ السلة
    public static function clear(): void
    {
        session()->forget(self::KEY);
    }
}

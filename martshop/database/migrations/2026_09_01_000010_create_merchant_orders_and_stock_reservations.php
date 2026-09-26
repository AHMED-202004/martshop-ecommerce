<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('subtotal', 12, 2)->default(0)->after('user_id');
            $table->decimal('shipping_fee', 12, 2)->default(0)->after('subtotal');
            $table->char('currency', 3)->default('ILS')->after('total');
            $table->timestamp('reservation_expires_at')->nullable()->after('payment_method')->index();
            $table->string('checkout_token', 64)->nullable()->after('reservation_expires_at')->unique();
        });

        Schema::create('marketplace_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string');
            $table->string('group')->nullable()->index();
            $table->text('description')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('merchant_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // Null represents transitional platform/demo catalogue items.
            $table->foreignId('merchant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('group_key');
            $table->string('status')->default('pending_confirmation')->index();
            $table->decimal('product_subtotal', 12, 2)->default(0);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('service_fee', 12, 2)->default(0);
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->char('currency', 3)->default('ILS');
            $table->foreignId('origin_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->json('commission_snapshot')->nullable();
            $table->json('delivery_snapshot')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'group_key']);
            $table->index(['merchant_id', 'status']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('merchant_order_id')->nullable()->after('order_id')
                ->constrained('merchant_orders')->nullOnDelete();
        });

        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merchant_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('product_offer_id')->nullable()->constrained('product_offers')->nullOnDelete();
            $table->foreignId('offer_variant_id')->nullable()->constrained('offer_variants')->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('status')->default('reserved')->index();
            $table->boolean('offer_stock_was_tracked')->default(false);
            $table->boolean('variant_stock_was_tracked')->default(false);
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->string('release_reason')->nullable();
            $table->timestamps();

            $table->index(['merchant_order_id', 'status']);
        });

        $now = now();
        DB::table('marketplace_settings')->insert([
            [
                'key' => 'checkout.shipping.flat_fee', 'value' => '20.00', 'type' => 'decimal',
                'group' => 'checkout', 'description' => 'Default checkout shipping fee in ILS.',
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'key' => 'checkout.shipping.free_threshold', 'value' => '250.00', 'type' => 'decimal',
                'group' => 'checkout', 'description' => 'Subtotal required for free shipping.',
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'key' => 'checkout.reservation_minutes', 'value' => '30', 'type' => 'integer',
                'group' => 'checkout', 'description' => 'Minutes before an unconfirmed stock reservation expires.',
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'key' => 'checkout.commission_percent', 'value' => '0.00', 'type' => 'decimal',
                'group' => 'checkout', 'description' => 'Default merchant commission percentage.',
                'created_at' => $now, 'updated_at' => $now,
            ],
        ]);

        // Keep historical orders readable through the new relationships.
        DB::table('orders')->orderBy('id')->chunkById(100, function ($orders) use ($now) {
            foreach ($orders as $order) {
                $items = DB::table('order_items')->where('order_id', $order->id)->get();
                $groups = $items->groupBy(fn ($item) => $item->merchant_id
                    ? 'merchant:'.$item->merchant_id
                    : 'platform');
                if ($groups->isEmpty()) {
                    $groups = collect(['platform' => collect()]);
                }

                $toMinor = fn ($value) => (int) round((float) $value * 100);
                $orderTotal = $toMinor($order->total);
                $itemsSubtotal = $items->sum(fn ($item) => $toMinor($item->price) * (int) $item->qty);
                if ($itemsSubtotal <= 0 || $itemsSubtotal > $orderTotal) {
                    $itemsSubtotal = $orderTotal;
                }
                $shipping = max(0, $orderTotal - $itemsSubtotal);

                DB::table('orders')->where('id', $order->id)->update([
                    'subtotal' => number_format($itemsSubtotal / 100, 2, '.', ''),
                    'shipping_fee' => number_format($shipping / 100, 2, '.', ''),
                ]);

                $remainingShipping = $shipping;
                $groupKeys = $groups->keys()->sort()->values();
                foreach ($groupKeys as $index => $groupKey) {
                    $groupItems = $groups[$groupKey];
                    $groupSubtotal = $groupItems->isEmpty()
                        ? $itemsSubtotal
                        : $groupItems->sum(fn ($item) => $toMinor($item->price) * (int) $item->qty);
                    $groupShipping = $index === $groupKeys->count() - 1
                        ? $remainingShipping
                        : intdiv($shipping * $groupSubtotal, max(1, $itemsSubtotal));
                    $remainingShipping -= $groupShipping;
                    $merchantId = str_starts_with($groupKey, 'merchant:')
                        ? (int) substr($groupKey, strlen('merchant:'))
                        : null;

                    $merchantOrderId = DB::table('merchant_orders')->insertGetId([
                        'order_id' => $order->id,
                        'merchant_id' => $merchantId,
                        'group_key' => $groupKey,
                        'status' => 'confirmed',
                        'product_subtotal' => number_format($groupSubtotal / 100, 2, '.', ''),
                        'delivery_fee' => number_format($groupShipping / 100, 2, '.', ''),
                        'service_fee' => 0,
                        'commission_amount' => 0,
                        'total' => number_format(($groupSubtotal + $groupShipping) / 100, 2, '.', ''),
                        'currency' => 'ILS',
                        'commission_snapshot' => json_encode(['policy' => 'legacy', 'percent' => '0.00']),
                        'delivery_snapshot' => json_encode([
                            'policy' => 'legacy',
                            'allocated_fee' => number_format($groupShipping / 100, 2, '.', ''),
                        ]),
                        'confirmed_at' => $order->created_at ?? $now,
                        'created_at' => $order->created_at ?? $now,
                        'updated_at' => $order->updated_at ?? $now,
                    ]);

                    if ($groupItems->isNotEmpty()) {
                        DB::table('order_items')
                            ->whereIn('id', $groupItems->pluck('id'))
                            ->update(['merchant_order_id' => $merchantOrderId]);
                    }
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merchant_order_id');
        });

        Schema::dropIfExists('merchant_orders');
        Schema::dropIfExists('marketplace_settings');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['checkout_token']);
            $table->dropIndex(['reservation_expires_at']);
            $table->dropColumn([
                'subtotal', 'shipping_fee', 'currency', 'reservation_expires_at', 'checkout_token',
            ]);
        });
    }
};

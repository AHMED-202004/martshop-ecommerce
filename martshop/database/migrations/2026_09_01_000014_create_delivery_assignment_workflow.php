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
            $table->json('delivery_address_snapshot')->nullable()->after('currency');
        });

        DB::table('orders')->orderBy('id')->chunkById(100, function ($orders) {
            foreach ($orders as $order) {
                $user = DB::table('users')->where('id', $order->user_id)->first();
                if (! $user) {
                    continue;
                }
                DB::table('orders')->where('id', $order->id)->update([
                    'delivery_address_snapshot' => json_encode([
                        'recipient_name' => trim(($user->first_name ?? '').' '.($user->last_name ?? ''))
                            ?: $user->name,
                        'governorate' => $user->governorate,
                        'city' => $user->city,
                        'address' => $user->address,
                        'mobile' => $user->mobile ?: $user->phone,
                        'alt_mobile' => $user->alt_mobile,
                        'source' => 'historical_user_profile_backfill',
                        'captured_at' => $order->created_at,
                    ], JSON_UNESCAPED_UNICODE),
                ]);
            }
        });

        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('merchant_order_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('delivery_worker_id')->constrained('users')->restrictOnDelete();
            $table->string('status')->default('assigned')->index();
            $table->string('reference')->unique();
            $table->string('assignment_key')->unique();
            $table->json('origin_snapshot');
            $table->json('destination_snapshot');
            $table->text('assignment_notes')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('accepted_at')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->index(['delivery_worker_id', 'status', 'assigned_at']);
            $table->index(['order_id', 'status']);
        });

        Schema::create('delivery_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->restrictOnDelete();
            $table->string('event_type')->index();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['delivery_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_events');
        Schema::dropIfExists('deliveries');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('delivery_address_snapshot');
        });
    }
};

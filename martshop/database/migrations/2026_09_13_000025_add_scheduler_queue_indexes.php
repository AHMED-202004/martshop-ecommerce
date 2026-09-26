<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->index(
                ['status', 'expires_at', 'merchant_order_id'],
                'stock_reservations_release_queue_index',
            );
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->index(
                ['status', 'settled_at', 'settlement_due_at', 'id'],
                'deliveries_settlement_queue_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->dropIndex('stock_reservations_release_queue_index');
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropIndex('deliveries_settlement_queue_index');
        });
    }
};

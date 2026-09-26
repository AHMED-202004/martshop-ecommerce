<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('deliveries', function (Blueprint $table) {
        $table->timestamp('heading_to_merchant_at')->nullable()->after('accepted_at');
        $table->timestamp('merchant_arrived_at')->nullable()->after('heading_to_merchant_at');
        $table->timestamp('out_for_delivery_at')->nullable()->after('picked_up_at');
        $table->timestamp('customer_arrived_at')->nullable()->after('out_for_delivery_at');
    }); }
    public function down(): void { Schema::table('deliveries', fn (Blueprint $table) => $table->dropColumn(['heading_to_merchant_at', 'merchant_arrived_at', 'out_for_delivery_at', 'customer_arrived_at'])); }
};

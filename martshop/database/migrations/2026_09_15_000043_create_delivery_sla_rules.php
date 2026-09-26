<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('delivery_sla_rules', function (Blueprint $table) {
            $table->id(); $table->string('name', 120); $table->foreignId('origin_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('destination_area', 120)->nullable(); $table->string('order_type', 40)->nullable(); $table->string('delivery_method', 40)->nullable();
            $table->decimal('minimum_distance_km', 8, 2)->nullable(); $table->decimal('maximum_distance_km', 8, 2)->nullable();
            $table->unsignedInteger('target_minutes'); $table->integer('priority')->default(0); $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->constrained('users'); $table->timestamps();
            $table->index(['origin_location_id', 'destination_area', 'is_active'], 'delivery_sla_route_active_index');
        });
        Schema::table('deliveries', function (Blueprint $table) {
            $table->foreignId('sla_rule_id')->nullable()->after('expected_delivery_at')->constrained('delivery_sla_rules')->nullOnDelete();
            $table->string('order_type', 40)->nullable()->after('sla_rule_id'); $table->string('delivery_method', 40)->nullable()->after('order_type');
            $table->decimal('distance_km', 8, 2)->nullable()->after('delivery_method');
        });
    }
    public function down(): void {
        Schema::table('deliveries', function (Blueprint $table) { $table->dropForeign(['sla_rule_id']); $table->dropColumn(['sla_rule_id', 'order_type', 'delivery_method', 'distance_km']); });
        Schema::dropIfExists('delivery_sla_rules');
    }
};

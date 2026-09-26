<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('deliveries', function (Blueprint $table) {
        $table->timestamp('expected_delivery_at')->nullable()->after('assigned_at')->index();
        $table->timestamp('failed_at')->nullable()->after('delivered_at');
        $table->timestamp('returned_at')->nullable()->after('failed_at');
        $table->timestamp('cancelled_at')->nullable()->after('returned_at');
        $table->foreignId('outcome_by')->nullable()->after('cancelled_at')->constrained('users');
        $table->string('outcome_reason', 1000)->nullable()->after('outcome_by');
        $table->index(['delivery_worker_id', 'status', 'assigned_at'], 'deliveries_worker_status_assigned_index');
    }); }
    public function down(): void { Schema::table('deliveries', function (Blueprint $table) {
        $table->dropIndex('deliveries_worker_status_assigned_index'); $table->dropIndex(['expected_delivery_at']);
        $table->dropForeign(['outcome_by']);
        $table->dropColumn(['expected_delivery_at', 'failed_at', 'returned_at', 'cancelled_at', 'outcome_by', 'outcome_reason']);
    }); }
};

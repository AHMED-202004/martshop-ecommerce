<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('deliveries', function (Blueprint $table) {
        $table->string('delay_reason', 40)->nullable()->after('distance_km'); $table->string('delay_responsibility', 40)->nullable()->after('delay_reason');
        $table->text('delay_note')->nullable()->after('delay_responsibility'); $table->foreignId('delay_recorded_by')->nullable()->after('delay_note')->constrained('users');
        $table->timestamp('delay_recorded_at')->nullable()->after('delay_recorded_by'); $table->index(['delay_responsibility', 'delay_recorded_at'], 'deliveries_delay_responsibility_time_index');
    }); }
    public function down(): void { Schema::table('deliveries', function (Blueprint $table) { $table->dropIndex('deliveries_delay_responsibility_time_index'); $table->dropForeign(['delay_recorded_by']); $table->dropColumn(['delay_reason', 'delay_responsibility', 'delay_note', 'delay_recorded_by', 'delay_recorded_at']); }); }
};

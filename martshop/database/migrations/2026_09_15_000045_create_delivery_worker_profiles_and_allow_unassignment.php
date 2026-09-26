<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('delivery_worker_profiles', function (Blueprint $table) { $table->id(); $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete(); $table->string('availability', 32)->default('available')->index(); $table->timestamp('availability_changed_at')->nullable(); $table->foreignId('availability_changed_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('last_activity_at')->nullable(); $table->timestamps(); });
        Schema::table('deliveries', function (Blueprint $table) { $table->dropForeign(['delivery_worker_id']); $table->unsignedBigInteger('delivery_worker_id')->nullable()->change(); $table->foreign('delivery_worker_id')->references('id')->on('users'); });
    }
    public function down(): void {
        if (\Illuminate\Support\Facades\DB::table('deliveries')->whereNull('delivery_worker_id')->exists()) throw new \RuntimeException('Cannot restore required delivery worker while unassigned deliveries exist.');
        Schema::table('deliveries', function (Blueprint $table) { $table->dropForeign(['delivery_worker_id']); $table->unsignedBigInteger('delivery_worker_id')->nullable(false)->change(); $table->foreign('delivery_worker_id')->references('id')->on('users'); });
        Schema::dropIfExists('delivery_worker_profiles');
    }
};

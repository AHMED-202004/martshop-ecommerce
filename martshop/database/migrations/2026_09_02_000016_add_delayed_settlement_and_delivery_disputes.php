<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            // Existing deliveries deliberately remain ineligible until reviewed; no retrospective release.
            $table->unsignedSmallInteger('settlement_hold_days')->nullable();
            $table->timestamp('settlement_due_at')->nullable()->index();
            $table->timestamp('settled_at')->nullable();
        });
        Schema::create('delivery_disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->string('status')->default('open')->index();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('close_reason')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_disputes');
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropIndex(['settlement_due_at']);
            $table->dropColumn(['settlement_hold_days', 'settlement_due_at', 'settled_at']);
        });
    }
};

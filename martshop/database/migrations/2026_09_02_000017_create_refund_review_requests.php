<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_dispute_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('sale_entry_id')->constrained('ledger_entries')->restrictOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference')->unique();
            $table->string('status')->default('requested')->index();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3);
            $table->json('amount_snapshot');
            $table->text('reason');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Never erase a financial review history during rollback.
        if (\Illuminate\Support\Facades\DB::table('refund_requests')->exists()) {
            throw new RuntimeException('Cannot roll back non-empty refund requests.');
        }
        Schema::dropIfExists('refund_requests');
    }
};

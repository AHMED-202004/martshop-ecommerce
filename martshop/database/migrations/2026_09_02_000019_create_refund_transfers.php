<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_request_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('refund_destination_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('destination_version');
            $table->text('recipient_snapshot');
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('merchant_amount');
            $table->unsignedBigInteger('commission_amount');
            $table->unsignedBigInteger('delivery_amount');
            $table->unsignedBigInteger('service_amount');
            $table->string('currency', 3);
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('prepared_at');
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('transferred_at')->nullable();
            $table->string('transaction_reference', 150)->nullable()->unique();
            $table->string('proof_path')->nullable();
            $table->string('proof_mime')->nullable();
            $table->unsignedBigInteger('proof_size')->nullable();
            $table->string('proof_sha256', 64)->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::table('refund_transfers')->exists()) {
            throw new RuntimeException('Cannot roll back non-empty refund transfer history.');
        }
        Schema::dropIfExists('refund_transfers');
    }
};

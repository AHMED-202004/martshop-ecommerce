<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('merchant_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('percentage', 5, 2);
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['merchant_id', 'category_id', 'is_active'], 'commission_rules_scope_index');
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('merchant_order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('reverses_entry_id')->nullable()->constrained('ledger_entries')->restrictOnDelete();
            $table->string('entry_type')->index();
            $table->string('direction')->index();
            // Financial amounts are stored in the currency's minor unit.
            $table->bigInteger('amount');
            $table->unsignedBigInteger('gross_amount')->default(0);
            $table->unsignedBigInteger('commission_amount')->default(0);
            $table->unsignedBigInteger('fee_amount')->default(0);
            $table->bigInteger('net_amount');
            $table->string('status')->index();
            $table->char('currency', 3)->default('ILS');
            $table->string('reference')->unique();
            $table->string('idempotency_key')->unique();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['merchant_id', 'status', 'created_at']);
            $table->index(['order_id', 'merchant_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('commission_rules');
    }
};

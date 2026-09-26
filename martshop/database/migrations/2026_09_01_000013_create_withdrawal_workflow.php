<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_payout_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->restrictOnDelete();
            $table->string('type')->index();
            $table->string('provider_name');
            $table->string('account_name');
            $table->text('account_identifier');
            $table->char('account_identifier_hash', 64);
            $table->string('last_four', 8)->nullable();
            $table->string('status')->default('pending')->index();
            $table->timestamp('submitted_at');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->index(['merchant_id', 'status']);
            $table->index(['merchant_id', 'account_identifier_hash'], 'payout_method_account_lookup');
        });

        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->restrictOnDelete();
            $table->foreignId('merchant_payout_method_id')
                ->constrained('merchant_payout_methods')->restrictOnDelete();
            // Amounts use the currency's minor unit.
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3)->default('ILS');
            $table->string('status')->default('requested')->index();
            $table->string('reference')->unique();
            $table->string('idempotency_key', 64)->unique();
            $table->json('destination_snapshot');
            $table->timestamp('requested_at');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('transferred_at')->nullable();
            $table->string('transaction_reference')->nullable()->unique();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->index(['merchant_id', 'status', 'requested_at']);
        });

        Schema::create('withdrawal_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('withdrawal_request_id')->unique()
                ->constrained()->restrictOnDelete();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->char('sha256', 64)->nullable()->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->foreignId('withdrawal_request_id')->nullable()->after('payment_id')
                ->constrained()->restrictOnDelete();
        });

        $now = now();
        $settings = [
            ['withdrawals.enabled', '0', 'boolean', 'Enable merchant withdrawal requests.'],
            ['withdrawals.minimum_amount', '50.00', 'decimal', 'Minimum withdrawal amount in ILS.'],
            ['withdrawals.maximum_amount', '5000.00', 'decimal', 'Maximum amount for one withdrawal in ILS.'],
            ['withdrawals.daily_limit', '5000.00', 'decimal', 'Maximum requested amount per merchant per day in ILS.'],
            ['withdrawals.weekly_limit', '15000.00', 'decimal', 'Maximum requested amount per merchant per week in ILS.'],
        ];
        foreach ($settings as [$key, $value, $type, $description]) {
            DB::table('marketplace_settings')->insert([
                'key' => $key,
                'value' => $value,
                'type' => $type,
                'group' => 'withdrawals',
                'description' => $description,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('marketplace_settings')->whereIn('key', [
            'withdrawals.enabled',
            'withdrawals.minimum_amount',
            'withdrawals.maximum_amount',
            'withdrawals.daily_limit',
            'withdrawals.weekly_limit',
        ])->delete();

        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('withdrawal_request_id');
        });
        Schema::dropIfExists('withdrawal_proofs');
        Schema::dropIfExists('withdrawal_requests');
        Schema::dropIfExists('merchant_payout_methods');
    }
};

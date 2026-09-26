<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->default('manual_transfer')->index();
            $table->boolean('is_active')->default(false)->index();
            $table->string('account_name')->nullable();
            $table->string('account_identifier')->nullable();
            $table->text('instructions')->nullable();
            $table->string('logo_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_status')->default('unpaid')->after('payment_method')->index();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
            $table->foreignId('payment_method_id')->nullable()->after('user_id')
                ->constrained('payment_methods')->nullOnDelete();
            $table->string('open_key')->nullable()->after('payment_method_id')->unique();
            $table->string('idempotency_key', 64)->nullable()->after('open_key')->unique();
            $table->string('sender_name')->nullable()->after('provider_ref');
            $table->string('sender_account')->nullable()->after('sender_name');
            $table->timestamp('transferred_at')->nullable()->after('sender_account');
            $table->foreignId('reviewed_by')->nullable()->after('meta')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_notes')->nullable()->after('reviewed_at');
            $table->timestamp('accepted_at')->nullable()->after('review_notes');
            $table->timestamp('rejected_at')->nullable()->after('accepted_at');
            $table->unsignedInteger('lock_version')->default(0)->after('rejected_at');

            $table->index(['order_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('payment_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('sha256', 64)->nullable()->index();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_proofs');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['order_id', 'status']);
            $table->dropUnique(['idempotency_key']);
            $table->dropUnique(['open_key']);
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropConstrainedForeignId('payment_method_id');
            $table->dropConstrainedForeignId('user_id');
            $table->dropConstrainedForeignId('order_id');
            $table->dropColumn([
                'open_key', 'idempotency_key', 'sender_name', 'sender_account',
                'transferred_at', 'reviewed_at', 'review_notes', 'accepted_at',
                'rejected_at', 'lock_version',
            ]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['payment_status']);
            $table->dropColumn('payment_status');
        });

        Schema::dropIfExists('payment_methods');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('created_by_merchant_id')->nullable()->after('category_id')
                ->constrained('merchants')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->after('metadata');
            $table->timestamp('reviewed_at')->nullable()->after('submitted_at');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')
                ->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable()->after('reviewed_by');
        });

        Schema::table('product_offers', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('metadata');
            $table->timestamp('reviewed_at')->nullable()->after('submitted_at');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')
                ->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable()->after('reviewed_by');
            $table->unique(['product_id', 'merchant_id']);
        });
    }

    public function down(): void
    {
        Schema::table('product_offers', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'merchant_id']);
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['submitted_at', 'reviewed_at', 'review_notes']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropConstrainedForeignId('created_by_merchant_id');
            $table->dropColumn(['submitted_at', 'reviewed_at', 'review_notes']);
        });
    }
};

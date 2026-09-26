<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Existing UI stores shekel values (including decimals), despite the old integer schema.
            $table->decimal('price', 12, 2)->change();
            $table->decimal('sale_price', 12, 2)->nullable()->change();
            $table->text('description')->nullable()->after('name');
            $table->string('model')->nullable()->after('slug')->index();
            $table->json('specifications')->nullable()->after('model');
            $table->string('source')->nullable()->after('specifications')->index();
            $table->string('source_key')->nullable()->after('source');
            $table->json('metadata')->nullable()->after('source_key');
            $table->unique(['source', 'source_key']);
        });

        Schema::create('category_product', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->primary(['category_id', 'product_id']);
            $table->index(['product_id', 'is_primary']);
        });

        Schema::create('product_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // Null is reserved for transitional platform/legacy offers only.
            $table->foreignId('merchant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('price', 12, 2);
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->char('currency', 3)->default('ILS');
            $table->unsignedInteger('stock')->nullable();
            $table->string('status')->default('active')->index();
            $table->unsignedSmallInteger('preparation_time_days')->nullable();
            $table->text('warranty')->nullable();
            $table->timestamp('last_confirmed_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->string('source')->nullable()->index();
            $table->string('source_key')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['source', 'source_key']);
            $table->index(['product_id', 'status']);
            $table->index(['merchant_id', 'status']);
        });

        Schema::create('offer_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_offer_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->nullable()->unique();
            $table->json('attributes');
            $table->decimal('price', 12, 2)->nullable();
            $table->unsignedInteger('stock')->nullable();
            $table->string('status')->default('active')->index();
            $table->string('source_key');
            $table->timestamps();

            $table->unique(['product_offer_id', 'source_key']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('order_id')
                ->constrained()->nullOnDelete();
            $table->foreignId('product_offer_id')->nullable()->after('product_id')
                ->constrained('product_offers')->nullOnDelete();
            $table->foreignId('merchant_id')->nullable()->after('product_offer_id')
                ->constrained()->nullOnDelete();
            $table->json('variant_snapshot')->nullable()->after('image');
            $table->json('offer_snapshot')->nullable()->after('variant_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merchant_id');
            $table->dropConstrainedForeignId('product_offer_id');
            $table->dropConstrainedForeignId('product_id');
            $table->dropColumn(['variant_snapshot', 'offer_snapshot']);
        });

        Schema::dropIfExists('offer_variants');
        Schema::dropIfExists('product_offers');
        Schema::dropIfExists('category_product');

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['source', 'source_key']);
            $table->dropIndex(['model']);
            $table->dropIndex(['source']);
            $table->dropColumn([
                'description', 'model', 'specifications', 'source', 'source_key', 'metadata',
            ]);
            $table->unsignedInteger('price')->change();
            $table->unsignedInteger('sale_price')->nullable()->change();
        });
    }
};

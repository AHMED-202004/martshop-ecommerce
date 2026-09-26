<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'status')) {
                $table->string('status', 20)->default('active')->index();
            }
            if (!Schema::hasColumn('products', 'is_super_deal')) {
                $table->boolean('is_super_deal')->default(false)->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'is_super_deal')) {
                $table->dropColumn('is_super_deal');
            }
            if (Schema::hasColumn('products', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_change_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('proposed_image_size')->nullable()->after('proposed_image_path');
            $table->char('proposed_image_sha256', 64)->nullable()->after('proposed_image_size');
        });
    }

    public function down(): void
    {
        Schema::table('product_change_requests', function (Blueprint $table) {
            $table->dropColumn(['proposed_image_size', 'proposed_image_sha256']);
        });
    }
};

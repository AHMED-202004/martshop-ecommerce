<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('submission_image_disk')->nullable()->after('image');
            $table->string('submission_image_path')->nullable()->after('submission_image_disk');
            $table->unsignedBigInteger('submission_image_size')->nullable()->after('submission_image_path');
            $table->char('submission_image_sha256', 64)->nullable()->after('submission_image_size');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'submission_image_disk', 'submission_image_path',
                'submission_image_size', 'submission_image_sha256',
            ]);
        });
    }
};

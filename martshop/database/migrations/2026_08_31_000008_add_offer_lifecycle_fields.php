<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_offers', function (Blueprint $table) {
            $table->unsignedInteger('lock_version')->default(0)->after('review_notes');
            $table->timestamp('paused_at')->nullable()->after('lock_version');
            $table->foreignId('paused_by')->nullable()->after('paused_at')
                ->constrained('users')->nullOnDelete();
            $table->string('pause_reason')->nullable()->after('paused_by');
        });
    }

    public function down(): void
    {
        Schema::table('product_offers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paused_by');
            $table->dropColumn(['lock_version', 'paused_at', 'pause_reason']);
        });
    }
};

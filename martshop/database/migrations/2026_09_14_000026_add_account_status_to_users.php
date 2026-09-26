<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('account_status', 32)->default('active')->index();
            $table->timestamp('account_status_changed_at')->nullable();
            $table->foreignId('account_status_changed_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['account_status_changed_by']);
            $table->dropIndex(['account_status']);
            $table->dropColumn(['account_status', 'account_status_changed_at', 'account_status_changed_by']);
        });
    }
};

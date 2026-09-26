<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->foreignId('resolved_by')->nullable()->after('resolved_at')->constrained('users');
            $table->foreignId('closed_by')->nullable()->after('closed_at')->constrained('users');
            $table->index(['resolved_by', 'resolved_at'], 'contact_messages_resolver_time_index');
            $table->index(['closed_by', 'closed_at'], 'contact_messages_closer_time_index');
        });
    }

    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropForeign(['resolved_by']);
            $table->dropForeign(['closed_by']);
            $table->dropIndex('contact_messages_resolver_time_index');
            $table->dropIndex('contact_messages_closer_time_index');
            $table->dropColumn(['resolved_by', 'closed_by']);
        });
    }
};

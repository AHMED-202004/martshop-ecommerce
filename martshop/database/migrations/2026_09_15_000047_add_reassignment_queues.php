<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_tasks', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->unsignedBigInteger('assigned_to')->nullable()->change();
            $table->foreign('assigned_to')->references('id')->on('users')->restrictOnDelete();
            $table->foreignId('queue_department_id')->nullable()->after('assigned_to')->constrained('staff_departments')->nullOnDelete();
        });
        Schema::table('contact_messages', fn (Blueprint $table) => $table->foreignId('queue_department_id')->nullable()->constrained('staff_departments')->nullOnDelete());
        Schema::table('deliveries', fn (Blueprint $table) => $table->foreignId('queue_department_id')->nullable()->constrained('staff_departments')->nullOnDelete());
    }

    public function down(): void
    {
        if (DB::table('staff_tasks')->whereNull('assigned_to')->exists()) throw new RuntimeException('Cannot require task assignee while queued tasks exist.');
        Schema::table('deliveries', fn (Blueprint $table) => $table->dropConstrainedForeignId('queue_department_id'));
        Schema::table('contact_messages', fn (Blueprint $table) => $table->dropConstrainedForeignId('queue_department_id'));
        Schema::table('staff_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('queue_department_id');
            $table->dropForeign(['assigned_to']);
            $table->unsignedBigInteger('assigned_to')->nullable(false)->change();
            $table->foreign('assigned_to')->references('id')->on('users')->restrictOnDelete();
        });
    }
};

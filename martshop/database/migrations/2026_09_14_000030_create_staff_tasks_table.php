<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->string('task_type', 60)->index();
            $table->string('priority', 20)->default('normal')->index();
            $table->foreignId('assigned_to')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableMorphs('related');
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('status', 20)->default('assigned')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['assigned_to', 'status', 'due_at'], 'staff_tasks_assignee_status_due_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_tasks');
    }
};

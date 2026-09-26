<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('catalog_review_decisions', function (Blueprint $table) {
        $table->id(); $table->string('subject_type', 191); $table->unsignedBigInteger('subject_id');
        $table->foreignId('reviewer_id')->constrained('users'); $table->string('from_status', 40); $table->string('to_status', 40);
        $table->string('origin', 40)->default('direct'); $table->timestamp('submitted_at')->nullable(); $table->timestamp('decided_at');
        $table->boolean('is_reversal')->default(false);
        $table->index(['reviewer_id', 'decided_at'], 'catalog_decisions_reviewer_time_index');
        $table->index(['subject_type', 'subject_id', 'decided_at'], 'catalog_decisions_subject_time_index');
        $table->index(['is_reversal', 'decided_at'], 'catalog_decisions_reversal_time_index');
    }); }
    public function down(): void { Schema::dropIfExists('catalog_review_decisions'); }
};

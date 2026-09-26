<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_destinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_request_id')->constrained()->restrictOnDelete();
            // One pending/verified destination per refund; retain rejected/revoked history.
            $table->string('active_key')->nullable()->unique();
            $table->text('recipient_snapshot');
            $table->string('last_four', 4);
            $table->string('status')->default('pending')->index();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();
            $table->index(['refund_request_id', 'id']);
        });
    }

    public function down(): void
    {
        if (DB::table('refund_destinations')->exists()) {
            throw new RuntimeException('Cannot roll back non-empty refund destination history.');
        }
        Schema::dropIfExists('refund_destinations');
    }
};

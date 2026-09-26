<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund_transfers', function (Blueprint $table) {
            $table->string('active_key')->nullable()->unique();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->index('refund_request_id');
        });
        DB::table('refund_transfers')->orderBy('id')->chunkById(200, function ($transfers) {
            foreach ($transfers as $transfer) {
                DB::table('refund_transfers')->where('id', $transfer->id)
                    ->update(['active_key' => 'refund:'.$transfer->refund_request_id]);
            }
        });
        Schema::table('refund_transfers', fn (Blueprint $table) => $table->dropUnique(['refund_request_id']));
    }

    public function down(): void
    {
        if (DB::table('refund_transfers')->exists()) {
            throw new RuntimeException('Cannot roll back non-empty refund transfer history.');
        }
        Schema::table('refund_transfers', function (Blueprint $table) {
            $table->unique('refund_request_id');
            $table->dropIndex(['refund_request_id']);
            $table->dropForeign(['cancelled_by']);
            $table->dropUnique(['active_key']);
            $table->dropColumn(['active_key', 'cancelled_by', 'cancelled_at', 'cancellation_reason']);
        });
    }
};

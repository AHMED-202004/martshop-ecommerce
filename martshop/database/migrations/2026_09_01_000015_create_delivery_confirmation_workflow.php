<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->text('confirmation_pin')->nullable()->after('assignment_notes');
            $table->string('confirmation_pin_hash')->nullable()->after('confirmation_pin');
            $table->timestamp('picked_up_at')->nullable()->after('accepted_at');
            $table->timestamp('in_transit_at')->nullable()->after('picked_up_at');
            $table->timestamp('delivered_at')->nullable()->after('in_transit_at');
            $table->text('delivery_note')->nullable()->after('delivered_at');
        });

        DB::table('deliveries')->orderBy('id')->chunkById(100, function ($deliveries) {
            foreach ($deliveries as $delivery) {
                $pin = (string) random_int(1000, 9999);
                DB::table('deliveries')->where('id', $delivery->id)->update([
                    'confirmation_pin' => Crypt::encryptString($pin),
                    'confirmation_pin_hash' => Hash::make($pin),
                ]);
            }
        });

        Schema::create('delivery_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->unique()->constrained()->restrictOnDelete();
            $table->string('disk')->default('local');
            $table->string('path')->unique();
            $table->string('original_name')->nullable();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_proofs');
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn([
                'confirmation_pin', 'confirmation_pin_hash', 'picked_up_at',
                'in_transit_at', 'delivered_at', 'delivery_note',
            ]);
        });
    }
};

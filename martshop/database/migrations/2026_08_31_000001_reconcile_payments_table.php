<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->id();
                $table->string('order_no');
                $table->unsignedBigInteger('amount');
                $table->string('currency', 3)->default('ILS');
                $table->string('provider')->default('manual');
                $table->string('provider_ref')->nullable();
                $table->string('status')->default('pending')->index();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique(['provider', 'provider_ref'], 'payments_provider_reference_unique');
            });

            return;
        }

        if (!Schema::hasIndex('payments', ['provider', 'provider_ref'], 'unique')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->unique(['provider', 'provider_ref'], 'payments_provider_reference_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payments') && Schema::hasIndex('payments', 'payments_provider_reference_unique')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropUnique('payments_provider_reference_unique');
            });
        }
    }
};

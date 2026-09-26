<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasIndex('users', ['phone'], 'unique')) {
            return;
        }

        $hasDuplicates = DB::table('users')
            ->select('phone')
            ->whereNotNull('phone')
            ->groupBy('phone')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicates) {
            throw new RuntimeException('Cannot enforce unique login phones while duplicate user phone values exist.');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('phone', 'users_phone_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasIndex('users', 'users_phone_unique')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_phone_unique');
        });
    }
};

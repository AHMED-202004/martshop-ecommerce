<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('gender')->nullable();
            $table->date('dob')->nullable();
            $table->string('governorate')->nullable();
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->string('mobile')->nullable();
            $table->string('alt_mobile')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name', 'last_name', 'phone', 'gender', 'dob',
                'governorate', 'city', 'address', 'mobile', 'alt_mobile',
            ]);
        });
    }
};

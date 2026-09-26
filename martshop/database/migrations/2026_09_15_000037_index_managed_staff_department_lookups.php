<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_departments', function (Blueprint $table) {
            $table->index('manager_id', 'staff_departments_manager_id_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('staff_department_id', 'users_staff_department_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_staff_department_id_index');
        });

        Schema::table('staff_departments', function (Blueprint $table) {
            $table->dropIndex('staff_departments_manager_id_index');
        });
    }
};

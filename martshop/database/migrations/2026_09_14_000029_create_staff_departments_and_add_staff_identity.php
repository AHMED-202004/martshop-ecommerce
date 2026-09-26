<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_number', 30)->nullable()->unique();
            $table->string('job_title', 120)->nullable();
            $table->foreignId('staff_department_id')->nullable()
                ->constrained('staff_departments')->nullOnDelete();
        });

        $staffIds = DB::table('users')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('role_user')
                    ->join('roles', 'roles.id', '=', 'role_user.role_id')
                    ->whereColumn('role_user.user_id', 'users.id')
                    ->where(function ($roles) {
                        $roles->whereIn('roles.slug', ['admin', 'delivery-worker'])
                            ->orWhereExists(function ($permissions) {
                                $permissions->selectRaw('1')
                                    ->from('permission_role')
                                    ->whereColumn('permission_role.role_id', 'roles.id');
                            });
                    });
            })
            ->orWhereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('permission_user')
                    ->whereColumn('permission_user.user_id', 'users.id');
            })
            ->orderBy('id')
            ->pluck('id');

        foreach ($staffIds as $staffId) {
            DB::table('users')->where('id', $staffId)->update([
                'employee_number' => 'EMP-'.str_pad((string) $staffId, 6, '0', STR_PAD_LEFT),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('staff_department_id');
            $table->dropUnique(['employee_number']);
            $table->dropColumn(['employee_number', 'job_title']);
        });

        Schema::dropIfExists('staff_departments');
    }
};

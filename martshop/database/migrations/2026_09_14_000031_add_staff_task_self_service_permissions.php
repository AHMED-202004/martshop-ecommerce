<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSIONS = [
        'staff-tasks.view-own' => 'View own staff tasks',
        'staff-tasks.update-own' => 'Update own staff tasks',
    ];

    public function up(): void
    {
        Schema::table('staff_tasks', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(0);
        });

        foreach (self::PERMISSIONS as $slug => $name) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                ['name' => $name, 'group' => 'staff-tasks', 'updated_at' => now(), 'created_at' => now()],
            );
        }

        $permissionIds = DB::table('permissions')->whereIn('slug', array_keys(self::PERMISSIONS))->pluck('id');
        $roleIds = DB::table('roles')->whereIn('slug', ['admin', 'delivery-worker'])->pluck('id');
        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('slug', array_keys(self::PERMISSIONS))->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permission_user')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        Schema::table('staff_tasks', function (Blueprint $table) {
            $table->dropColumn('version');
        });
    }
};

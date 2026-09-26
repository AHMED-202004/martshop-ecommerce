<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUG = 'staff-departments.view-managed';

    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['slug' => self::SLUG],
            [
                'name' => 'View managed department staff',
                'group' => 'staff-departments',
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');
        $permissionId = DB::table('permissions')->where('slug', self::SLUG)->value('id');
        if ($adminRoleId && $permissionId) {
            DB::table('permission_role')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $adminRoleId,
            ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('slug', self::SLUG)->value('id');
        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permission_user')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};

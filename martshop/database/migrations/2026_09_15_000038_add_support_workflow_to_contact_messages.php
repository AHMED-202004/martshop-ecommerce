<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSION = 'contact-messages.manage';

    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->string('status', 32)->default('open')->index();
            $table->foreignId('assigned_to')->nullable()->index()->constrained('users');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('sla_due_at')->nullable()->index();
            $table->unsignedInteger('reopened_count')->default(0);
            $table->unsignedInteger('lock_version')->default(0);
        });

        Schema::create('contact_message_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users');
            $table->text('body');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();
            $table->index(['contact_message_id', 'created_at'], 'contact_message_replies_ticket_created_index');
            $table->index(['author_id', 'created_at'], 'contact_message_replies_author_created_index');
        });

        DB::table('permissions')->updateOrInsert(
            ['slug' => self::PERMISSION],
            [
                'name' => 'Manage customer support tickets',
                'group' => 'support',
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');
        $permissionId = DB::table('permissions')->where('slug', self::PERMISSION)->value('id');
        if ($adminRoleId && $permissionId) {
            DB::table('permission_role')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $adminRoleId,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_message_replies');

        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->dropIndex(['status']);
            $table->dropIndex(['assigned_to']);
            $table->dropIndex(['sla_due_at']);
            $table->dropColumn([
                'status', 'assigned_to', 'assigned_at', 'first_response_at', 'resolved_at',
                'closed_at', 'sla_due_at', 'reopened_count', 'lock_version',
            ]);
        });

        $permissionId = DB::table('permissions')->where('slug', self::PERMISSION)->value('id');
        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permission_user')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};

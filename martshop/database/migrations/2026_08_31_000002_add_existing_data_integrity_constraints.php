<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index('user_id', 'orders_user_id_index');
            $table->foreign('user_id', 'orders_user_id_foreign')
                ->references('id')->on('users')->restrictOnDelete();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->index('order_id', 'order_items_order_id_index');
            $table->foreign('order_id', 'order_items_order_id_foreign')
                ->references('id')->on('orders')->cascadeOnDelete();
        });

        Schema::table('contact_messages', function (Blueprint $table) {
            $table->index('user_id', 'contact_messages_user_id_index');
            $table->foreign('user_id', 'contact_messages_user_id_foreign')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropForeign('contact_messages_user_id_foreign');
            $table->dropIndex('contact_messages_user_id_index');
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign('order_items_order_id_foreign');
            $table->dropIndex('order_items_order_id_index');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign('orders_user_id_foreign');
            $table->dropIndex('orders_user_id_index');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('topic');
            $table->string('contact'); // بريد أو هاتف
            $table->string('ref')->nullable(); // مرجعية الطلب
            $table->text('message');
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};

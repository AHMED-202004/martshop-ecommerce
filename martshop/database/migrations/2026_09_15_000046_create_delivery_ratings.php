<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void { Schema::dropIfExists('delivery_ratings'); }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('size_type', 10);   // 'alpha' أو 'num'
            $table->string('size_value');      // S,M,L أو 28,29,30...
            $table->string('color')->nullable();
            $table->boolean('in_stock')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('product_variants');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_data', function (Blueprint $table) {
            $table->id()->primary();
            $table->string('productName', 50);
            $table->string('productDesc');
            $table->string('productCode', 10)->unique();
            $table->timestamp('discontinued')->nullable();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('createdAt')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_data');
    }
};

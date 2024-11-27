<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_data', function (Blueprint $table) {
            $table->integer('productStock')->after('discontinued');
            $table->float('productCost')->after('productStock');;
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_data', function (Blueprint $table) {
            $table->dropColumn('productStock');
            $table->dropColumn('productCost');
        });
    }
};
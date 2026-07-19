<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The 1% lucky draw is a global feature — it applies to every product whose price
 * falls inside a club band, so vendors no longer opt in per product.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('allow_draw');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('allow_draw')->default(true)->after('allow_full_buy');
        });
    }
};

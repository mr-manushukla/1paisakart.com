<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A customer may hold several seats in one club pool, but each must be a
 * DIFFERENT product — the same item can never be booked twice by the same user
 * in the same pool. Enforced in the database so a race can't slip past it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('draw_entries', function (Blueprint $table) {
            $table->unique(['batch_id', 'user_id', 'product_id'], 'draw_entries_one_product_per_user_per_pool');
        });
    }

    public function down(): void
    {
        Schema::table('draw_entries', function (Blueprint $table) {
            $table->dropUnique('draw_entries_one_product_per_user_per_pool');
        });
    }
};

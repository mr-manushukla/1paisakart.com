<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('subtotal');          // paise
            $table->unsignedBigInteger('wallet_applied')->default(0);
            $table->unsignedBigInteger('payable');           // paise charged to gateway
            $table->enum('status', ['pending', 'paid', 'fulfilled', 'cancelled'])->default('pending')->index();
            $table->enum('source', ['buy', 'draw_win'])->default('buy');
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('qty')->default(1);
            $table->unsignedBigInteger('unit_price');        // paise
            $table->unsignedBigInteger('line_total');        // paise
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draw_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('batch_no');
            $table->unsignedInteger('size')->default(100);
            $table->unsignedBigInteger('entry_price');       // paise; = 1% of listed_price
            $table->enum('status', ['open', 'filled', 'drawn', 'cancelled'])->default('open')->index();
            $table->unsignedInteger('filled_count')->default(0);
            $table->unsignedBigInteger('winner_entry_id')->nullable(); // FK added after draw_entries exists
            $table->timestamp('drawn_at')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'batch_no']);
        });

        Schema::create('draw_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('draw_batches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');            // paise paid (real money only)
            $table->enum('status', ['active', 'won', 'refunded'])->default('active')->index();
            $table->timestamps();
            $table->index(['batch_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draw_entries');
        Schema::dropIfExists('draw_batches');
    }
};

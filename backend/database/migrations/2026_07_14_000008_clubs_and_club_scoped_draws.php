<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Campaign revision: draw pools are per PRICE-BAND CLUB (not per product).
 * Rebuilds the draw tables — existing draw data is demo-only and is dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clubs', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->unsignedBigInteger('min_price'); // paise, inclusive
            $table->unsignedBigInteger('max_price'); // paise, inclusive
            $table->timestamps();
            $table->unique(['min_price', 'max_price']);
            $table->index('min_price');
        });

        // Reference data — seeded here so production gets it from `migrate`.
        $bands = [[100, 1000], [1001, 5000]];
        for ($lo = 5001; $lo <= 495001; $lo += 5000) {
            $bands[] = [$lo, $lo + 4999];
        }
        $now = now();
        DB::table('clubs')->insert(array_map(fn ($b) => [
            'label' => '₹'.number_format($b[0]).' – ₹'.number_format($b[1]),
            'min_price' => $b[0] * 100,
            'max_price' => $b[1] * 100,
            'created_at' => $now,
            'updated_at' => $now,
        ], $bands));

        // Drop children first so no dropForeign() is needed (SQLite can't do that).
        // All three hold demo-only data at this stage.
        Schema::dropIfExists('payments');
        Schema::dropIfExists('draw_entries');
        Schema::dropIfExists('draw_batches');

        Schema::create('draw_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('batch_no');
            $table->unsignedInteger('size')->default(100);
            $table->enum('status', ['open', 'drawn', 'cancelled'])->default('open')->index();
            $table->unsignedInteger('filled_count')->default(0);
            $table->unsignedBigInteger('winner_entry_id')->nullable();
            $table->timestamp('drawn_at')->nullable();
            $table->timestamps();
            $table->unique(['club_id', 'batch_no']);
        });

        Schema::create('draw_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('draw_batches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete(); // the product they booked
            $table->unsignedBigInteger('amount');          // 1% advance actually paid (paise)
            $table->enum('status', [
                'active',        // pool still open
                'won',           // won — product fulfilled, platform covers the rest
                'lost_pending',  // didn't win — awaiting Option A / Option B
                'converted',     // Option A: paid the remaining 99%
                'credited',      // Option B (or auto after the window): 1% moved to wallet
                'refunded',      // batch cancelled by admin
            ])->default('active')->index();
            $table->timestamp('choice_deadline_at')->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['batch_id', 'user_id']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('entry_id')->nullable()->constrained('draw_entries')->nullOnDelete();
            $table->unsignedBigInteger('amount');            // paise (real money only)
            $table->string('gateway')->default('stub');
            $table->enum('status', ['pending', 'success', 'failed'])->default('success');
            $table->string('ref')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('draw_entries');
        Schema::dropIfExists('draw_batches');
        Schema::dropIfExists('clubs');
    }
};

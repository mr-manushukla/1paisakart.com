<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only ledger. Source of truth for users.wallet_balance.
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['draw_refund', 'purchase_debit', 'admin_adjust']);
            $table->bigInteger('amount');                    // signed paise: + credit, - debit
            $table->unsignedBigInteger('balance_after');
            $table->string('ref_type')->nullable();          // e.g. draw_batch, order
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index('user_id');
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

        // Simple key-value settings (platform_fee_pct, etc.)
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('wallet_transactions');
    }
};

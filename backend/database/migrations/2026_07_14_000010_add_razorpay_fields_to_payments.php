<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payments become real: a row is created when a Razorpay order is opened
 * (status=pending) and completed only after the signature is verified.
 * `intent` + `payload` describe what to execute once payment succeeds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('intent')->nullable()->after('gateway');   // checkout | draw | balance
            $table->json('payload')->nullable()->after('intent');     // server-computed request details
            $table->string('rzp_order_id')->nullable()->after('payload')->index();
            $table->string('rzp_payment_id')->nullable()->after('rzp_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['intent', 'payload', 'rzp_order_id', 'rzp_payment_id']);
        });
    }
};

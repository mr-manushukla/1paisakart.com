<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Claiming a win.
 *
 * A prize won in kind still attracts tax under s.194B, and the payer may only
 * release the prize once that tax is accounted for. So a win is no longer
 * dispatched the moment it is drawn: the winner claims it — picks a delivery
 * address and pays the TDS — and only then does the order move on to dispatch.
 *
 * Wins drawn BEFORE this change were fulfilled under the old rule, so they are
 * backfilled as already claimed. Asking those winners for tax retroactively on
 * a prize they have already received would be wrong.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('draw_entries', function (Blueprint $table) {
            $table->timestamp('claimed_at')->nullable()->after('choice_deadline_at');
        });

        Schema::table('orders', function (Blueprint $table) {
            // Tax collected from the winner and payable onward to the government.
            $table->unsignedBigInteger('tds_amount')->default(0)->after('payable');
        });

        // updated_at on a won entry is the moment settle() marked it won.
        DB::table('draw_entries')->where('status', 'won')->update(['claimed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('tds_amount');
        });
        Schema::table('draw_entries', function (Blueprint $table) {
            $table->dropColumn('claimed_at');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two separate mechanisms:
 *  - product discount: `sale_price` under the MRP (`listed_price`). The sale price
 *    becomes the product's EFFECTIVE price, so it drives the 1% advance and the
 *    price-band club too.
 *  - coupons: codes applied at checkout, issued by a vendor (own products) or by
 *    the admin (platform-wide).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Sale price in paise; when set (and below listed_price) it is what the
            // customer actually pays, and what the 1% advance is calculated from.
            $table->unsignedBigInteger('sale_price')->nullable()->after('listed_price');
        });

        // What the product cost when the seat was booked, so a later price change
        // can't alter the balance an existing booker owes.
        Schema::table('draw_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('product_price')->nullable()->after('amount');
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->nullable()->constrained()->cascadeOnDelete(); // null = platform-wide
            $table->string('code')->unique();
            $table->enum('type', ['percent', 'fixed']);
            $table->unsignedInteger('value');                              // percent 1–100, or paise
            $table->unsignedBigInteger('min_order')->default(0);           // paise
            $table->unsignedBigInteger('max_discount')->nullable();        // paise, caps a percent coupon
            $table->unsignedInteger('usage_limit')->nullable();            // total redemptions
            $table->unsignedInteger('per_user_limit')->default(1);
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('discount');                        // paise actually given
            $table->timestamps();
            $table->index(['coupon_id', 'user_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('subtotal')->constrained()->nullOnDelete();
            $table->unsignedBigInteger('discount')->default(0)->after('coupon_id'); // paise off the subtotal
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn('discount');
        });
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
        Schema::table('draw_entries', fn (Blueprint $t) => $t->dropColumn('product_price'));
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('sale_price'));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery addresses (collected at checkout, managed from the account drawer)
 * and per-product serviceability.
 *
 * Serviceability is stored as PIN *prefixes* so a vendor can say "110" (all of
 * Delhi) or "122001" (one exact PIN) with the same mechanism. A product with NO
 * rows here ships Pan India. Matching is an IN() against the prefixes of the
 * customer's PIN, which stays indexable — see Product::scopeServiceableIn().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 40)->nullable();     // Home / Work
            $table->string('name', 120);
            $table->string('phone', 20);
            $table->string('line1', 255);
            $table->string('line2', 255)->nullable();
            $table->string('city', 120);
            $table->string('state', 120);
            $table->string('pincode', 10)->index();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('product_service_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('prefix', 6);                 // "110" = Delhi, "122001" = exact PIN
            $table->timestamps();
            $table->unique(['product_id', 'prefix']);
            $table->index('prefix');
        });

        // Orders remember where they were shipped, independent of later edits.
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('address_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        // Phone doubles as a login handle, so it has to be unique and indexed.
        Schema::table('users', function (Blueprint $table) {
            $table->unique('phone');
        });

        // Admin-created categories need their own tile icon; hardcoding a name->emoji
        // map meant any new category fell back to a generic tag.
        Schema::table('categories', function (Blueprint $table) {
            $table->string('icon', 16)->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('address_id');
        });
        Schema::dropIfExists('product_service_areas');
        Schema::dropIfExists('addresses');
    }
};

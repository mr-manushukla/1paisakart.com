<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'shop_id', 'category_id', 'name', 'brand', 'slug', 'description', 'image', 'images', 'specs',
        'listed_price', 'stock', 'allow_full_buy', 'platform_fee_pct', 'status',
    ];

    protected function casts(): array
    {
        return [
            'listed_price' => 'integer',
            'stock' => 'integer',
            'allow_full_buy' => 'boolean',
            'platform_fee_pct' => 'decimal:2',
            'images' => 'array',
            'specs' => 'array',
        ];
    }

    /** Gallery images (falls back to the single primary image). */
    public function gallery(): array
    {
        return ! empty($this->images) ? $this->images : array_filter([$this->image]);
    }

    public function shop(): BelongsTo { return $this->belongsTo(Shop::class); }
    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
    public function drawEntries(): HasMany { return $this->hasMany(DrawEntry::class); }
    public function reviews(): HasMany { return $this->hasMany(Review::class); }

    /** The price-band club this product falls into (pools are club-scoped). */
    public function club(): ?Club
    {
        return Club::forPrice($this->listed_price);
    }

    /**
     * The 1% draw is a GLOBAL feature — no per-product opt-in. A product is
     * eligible when it's active and its price falls inside a club band.
     */
    public function drawEligible(): bool
    {
        return $this->status === 'active' && $this->club() !== null;
    }

    /** True if the user has an order (buy or draw win) containing this product — gates reviews. */
    public function purchasedBy(User $user): bool
    {
        return OrderItem::where('product_id', $this->id)
            ->whereHas('order', fn ($q) => $q->where('user_id', $user->id))
            ->exists();
    }

    /** Cost of one draw entry = entry_pct% of listed price (paise), floored. */
    public function entryPrice(): int
    {
        return intdiv($this->listed_price * (int) config('draw.entry_pct', 1), 100);
    }

    /** Max wallet credit applicable per unit on a 100% buy = wallet_cap_pct% of price. */
    public function maxWalletApplicable(): int
    {
        return intdiv($this->listed_price * (int) config('draw.wallet_cap_pct', 10), 100);
    }

    /** The currently open pool for this product's club (shared with other products in the band). */
    public function openBatch(): ?DrawBatch
    {
        $club = $this->club();

        return $club
            ? $club->batches()->where('status', 'open')->latest('id')->first()
            : null;
    }
}

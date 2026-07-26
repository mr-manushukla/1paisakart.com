<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'shop_id', 'category_id', 'name', 'brand', 'slug', 'description', 'image', 'images', 'specs',
        'listed_price', 'sale_price', 'stock', 'allow_full_buy', 'platform_fee_pct', 'status',
    ];

    protected function casts(): array
    {
        return [
            'listed_price' => 'integer',
            'sale_price' => 'integer',
            'stock' => 'integer',
            'allow_full_buy' => 'boolean',
            'platform_fee_pct' => 'decimal:2',
            'images' => 'array',
            'specs' => 'array',
        ];
    }

    /**
     * What the customer actually pays: the sale price when the vendor has
     * discounted, otherwise the listed price (MRP). This is the ONLY price money
     * should be calculated from — it drives the 1% advance and the club band too.
     */
    public function effectivePrice(): int
    {
        return $this->sale_price && $this->sale_price < $this->listed_price
            ? (int) $this->sale_price
            : (int) $this->listed_price;
    }

    public function onSale(): bool
    {
        return $this->effectivePrice() < $this->listed_price;
    }

    /** Whole-percent saving off the MRP, or null when not discounted. */
    public function discountPct(): ?int
    {
        if (! $this->onSale() || $this->listed_price < 1) {
            return null;
        }

        return (int) round(($this->listed_price - $this->effectivePrice()) / $this->listed_price * 100);
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
    public function serviceAreas(): HasMany { return $this->hasMany(ProductServiceArea::class); }

    /** No service areas at all means the product ships anywhere in India. */
    public function shipsPanIndia(): bool
    {
        return $this->serviceAreas()->count() === 0;
    }

    /**
     * Products deliverable to a PIN: those with no restriction, plus those
     * holding any prefix of that PIN.
     */
    public function scopeServiceableIn($query, ?string $pincode)
    {
        $pin = preg_replace('/\D/', '', (string) $pincode);
        if (strlen($pin) < 6) {
            return $query; // no PIN chosen (or incomplete) — don't filter
        }

        return $query->where(fn ($q) => $q
            ->whereDoesntHave('serviceAreas')
            ->orWhereHas('serviceAreas', fn ($s) => $s->whereIn('prefix', ProductServiceArea::prefixesOf($pin))));
    }

    /** The price-band club this product falls into — based on what it actually sells for. */
    public function club(): ?Club
    {
        return Club::forPrice($this->effectivePrice());
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

    /** Cost of one draw entry = entry_pct% of the effective (discounted) price, floored. */
    public function entryPrice(): int
    {
        return intdiv($this->effectivePrice() * (int) config('draw.entry_pct', 1), 100);
    }

    /** Max wallet credit applicable per unit on a 100% buy = wallet_cap_pct% of price. */
    public function maxWalletApplicable(): int
    {
        return intdiv($this->effectivePrice() * (int) config('draw.wallet_cap_pct', 10), 100);
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

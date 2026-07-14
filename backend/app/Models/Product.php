<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'shop_id', 'category_id', 'name', 'slug', 'description', 'image',
        'listed_price', 'stock', 'allow_full_buy', 'allow_draw', 'platform_fee_pct', 'status',
    ];

    protected function casts(): array
    {
        return [
            'listed_price' => 'integer',
            'stock' => 'integer',
            'allow_full_buy' => 'boolean',
            'allow_draw' => 'boolean',
            'platform_fee_pct' => 'decimal:2',
        ];
    }

    public function shop(): BelongsTo { return $this->belongsTo(Shop::class); }
    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
    public function batches(): HasMany { return $this->hasMany(DrawBatch::class); }

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

    public function openBatch(): ?DrawBatch
    {
        return $this->batches()->where('status', 'open')->latest('id')->first();
    }
}

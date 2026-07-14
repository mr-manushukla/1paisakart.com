<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DrawBatch extends Model
{
    protected $fillable = [
        'product_id', 'batch_no', 'size', 'entry_price', 'status',
        'filled_count', 'winner_entry_id', 'drawn_at',
    ];

    protected function casts(): array
    {
        return [
            'entry_price' => 'integer',
            'size' => 'integer',
            'filled_count' => 'integer',
            'drawn_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function entries(): HasMany { return $this->hasMany(DrawEntry::class, 'batch_id'); }
    public function winnerEntry(): BelongsTo { return $this->belongsTo(DrawEntry::class, 'winner_entry_id'); }

    public function isFull(): bool { return $this->filled_count >= $this->size; }
}

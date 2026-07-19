<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One pool of 100 bookings within a price-band club. Products in the pool may differ. */
class DrawBatch extends Model
{
    protected $fillable = [
        'club_id', 'batch_no', 'size', 'status', 'filled_count', 'winner_entry_id', 'drawn_at',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'filled_count' => 'integer',
            'drawn_at' => 'datetime',
        ];
    }

    public function club(): BelongsTo { return $this->belongsTo(Club::class); }
    public function entries(): HasMany { return $this->hasMany(DrawEntry::class, 'batch_id'); }
    public function winnerEntry(): BelongsTo { return $this->belongsTo(DrawEntry::class, 'winner_entry_id'); }

    public function isFull(): bool { return $this->filled_count >= $this->size; }
}

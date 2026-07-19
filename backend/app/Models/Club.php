<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A price band. Draw pools are per club, so different products of similar price share a pool. */
class Club extends Model
{
    protected $fillable = ['label', 'min_price', 'max_price'];

    protected function casts(): array
    {
        return ['min_price' => 'integer', 'max_price' => 'integer'];
    }

    public function batches(): HasMany { return $this->hasMany(DrawBatch::class); }

    /** The club whose band contains this price (paise), or null if out of range. */
    public static function forPrice(int $paise): ?self
    {
        return static::query()
            ->where('min_price', '<=', $paise)
            ->where('max_price', '>=', $paise)
            ->first();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DrawEntry extends Model
{
    protected $fillable = ['batch_id', 'user_id', 'amount', 'status'];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    public function batch(): BelongsTo { return $this->belongsTo(DrawBatch::class, 'batch_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}

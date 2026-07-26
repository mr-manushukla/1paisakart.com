<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A customer's delivery address. One may be flagged as the default. */
class Address extends Model
{
    protected $fillable = [
        'label', 'name', 'phone', 'line1', 'line2', 'city', 'state', 'pincode', 'is_default',
    ];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    /** "12 Main St, Flat 3, Gurugram, Haryana 122001" */
    public function oneLine(): string
    {
        return implode(', ', array_filter([
            $this->line1, $this->line2, $this->city, $this->state.' '.$this->pincode,
        ]));
    }
}

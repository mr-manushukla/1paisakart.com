<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One serviceable PIN prefix for a product. No rows at all = ships Pan India. */
class ProductServiceArea extends Model
{
    protected $fillable = ['product_id', 'prefix'];

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }

    /**
     * Every prefix of a PIN, longest last: "122001" -> 1, 12, 122, 1220, 12200, 122001.
     * A product is serviceable when it holds ANY of these.
     */
    public static function prefixesOf(string $pincode): array
    {
        $pin = preg_replace('/\D/', '', $pincode);
        $out = [];
        for ($i = 1; $i <= strlen($pin); $i++) {
            $out[] = substr($pin, 0, $i);
        }

        return $out;
    }
}

<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Masks a participant's name for public transparency views — enough to see a
 * real, distinct person, never their identity. Shared so the pool and winner
 * views mask identically (privacy must not drift between them).
 */
class NameMask
{
    /** "Customer 12" -> "Cu***12"; short names -> "C**". */
    public static function name(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return 'Guest';
        }
        if (Str::length($name) <= 3) {
            return Str::substr($name, 0, 1).'**';
        }

        return Str::substr($name, 0, 2).'***'.Str::substr($name, -2);
    }
}

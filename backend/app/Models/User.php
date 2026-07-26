<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

// role & wallet_balance are intentionally NOT fillable (privilege / money boundary).
#[Fillable(['name', 'email', 'password', 'phone', 'address'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'wallet_balance' => 'integer',
        ];
    }

    public function isAdmin(): bool { return $this->role === 'admin'; }
    public function isVendor(): bool { return $this->role === 'vendor'; }
    public function isCustomer(): bool { return $this->role === 'customer'; }

    public function shop(): HasOne { return $this->hasOne(Shop::class); }
    public function orders(): HasMany { return $this->hasMany(Order::class); }
    public function drawEntries(): HasMany { return $this->hasMany(DrawEntry::class); }
    public function walletTransactions(): HasMany { return $this->hasMany(WalletTransaction::class); }
    public function wishlists(): HasMany { return $this->hasMany(Wishlist::class); }
    public function addresses(): HasMany { return $this->hasMany(Address::class); }

    /** Draw participation at a glance — same numbers for the customer and for admin. */
    public function drawSummary(): array
    {
        $e = $this->drawEntries()->get(['id', 'product_id', 'batch_id', 'amount', 'status']);

        return [
            'pools_joined' => $e->pluck('batch_id')->unique()->count(),
            'bookings' => $e->count(),
            'products' => $e->pluck('product_id')->unique()->count(),
            'total_advanced' => (int) $e->sum('amount'),   // paise
            'active' => $e->where('status', 'active')->count(),
            'won' => $e->where('status', 'won')->count(),
            'awaiting_choice' => $e->where('status', 'lost_pending')->count(),
            'converted' => $e->where('status', 'converted')->count(),
            'credited' => $e->where('status', 'credited')->count(),
        ];
    }
}

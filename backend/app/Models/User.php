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
#[Fillable(['name', 'email', 'password'])]
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
}

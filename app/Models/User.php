<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'phone', 'skill_level', 'public_profile', 'avatar_path'])]
#[Hidden(['password', 'remember_token', 'google_sub'])]
class User extends Authenticatable
{
    protected $attributes = ['role' => 'customer', 'active' => true, 'wallet_balance' => 0, 'skill_level' => 'Sơ cấp', 'public_profile' => false];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'branch_id' => 'integer', 'loyalty_points' => 'integer', 'earned_points' => 'integer', 'phone_verified_at' => 'datetime', 'last_seen_at' => 'datetime',
            'active' => 'boolean', 'wallet_balance' => 'integer', 'public_profile' => 'boolean',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }
}

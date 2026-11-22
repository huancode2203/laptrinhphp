<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\AccountIdentity;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'phone', 'skill_level', 'public_profile', 'avatar_path', 'email_reminders', 'sms_reminders'])]
#[Hidden(['password', 'remember_token', 'google_sub', 'firebase_uid', 'phone_normalized', 'badgeMemberships'])]
class User extends Authenticatable
{
    protected $appends = ['google_connected', 'membership_badge'];

    protected $with = ['badgeMemberships.plan'];

    public function badgeMemberships(): HasMany
    {
        return $this->hasMany(Membership::class)->where('starts_at', '<=', now())->where('expires_at', '>', now())->whereIn('user_id', static::select('id')->where('role', 'customer'));
    }

    public function getMembershipBadgeAttribute(): ?array
    {
        $membership = $this->activeMembershipForDisplay();
        if (! $membership) {
            return null;
        }

        return ['label' => $membership->plan->name, 'tone' => $membership->plan->badge_tone, 'kind' => $membership->plan->price > 0 ? 'paid' : 'loyalty'];
    }

    public function activeMembershipForDisplay(): ?Membership
    {
        return $this->badgeMemberships->filter(fn ($member) => $member->plan !== null)->sortByDesc(fn ($member) => [$member->plan->discount_percent, $member->plan->price, $member->expires_at->timestamp])->first();
    }

    public function getGoogleConnectedAttribute(): bool
    {
        return (bool) $this->google_sub;
    }

    protected $attributes = ['role' => 'customer', 'active' => true, 'wallet_balance' => 0, 'skill_level' => 'Sơ cấp', 'public_profile' => false];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function phone(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => AccountIdentity::phone($value));
    }

    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $value) => mb_strtolower(trim($value)));
    }

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            AccountIdentity::assertAvailable($user);
            if ($user->isDirty('phone')) {
                $user->phone_normalized = $user->phone;
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'branch_id' => 'integer', 'loyalty_points' => 'integer', 'earned_points' => 'integer', 'phone_verified_at' => 'datetime', 'last_seen_at' => 'datetime',
            'active' => 'boolean', 'wallet_balance' => 'integer', 'public_profile' => 'boolean', 'email_reminders' => 'boolean', 'sms_reminders' => 'boolean',
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

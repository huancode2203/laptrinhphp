<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'district', 'ward', 'google_place_id', 'latitude', 'longitude', 'address', 'phone', 'amenities', 'active', 'opens_minute', 'closes_minute', 'peak_minute', 'booking_days', 'deposit_percent', 'payment_minutes', 'fixed_discount', 'image_path'])]
class Branch extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['amenities' => 'array', 'active' => 'boolean'];
    }

    public function courts(): HasMany
    {
        return $this->hasMany(Court::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}

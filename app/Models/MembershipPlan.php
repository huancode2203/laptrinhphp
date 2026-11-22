<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable(['name', 'price', 'discount_percent', 'duration_days', 'active', 'early_days', 'points_threshold'])]

class MembershipPlan extends Model
{
    use HasFactory;

    protected $appends = ['badge_tone'];

    public function getBadgeToneAttribute(): string
    {
        $name = Str::lower(Str::ascii($this->name, 'vi'));

        return match (true) {
            str_contains($name, 'vip') => 'vip',
            str_contains($name, 'gold'), str_contains($name, 'vang') => 'gold',
            str_contains($name, 'silver'), str_contains($name, 'bac') => 'silver',
            str_contains($name, 'bronze'), str_contains($name, 'dong') => 'bronze',
            default => 'emerald',
        };
    }

    protected function casts(): array
    {
        return ['price' => 'integer', 'discount_percent' => 'integer', 'duration_days' => 'integer', 'active' => 'boolean'];
    }
}

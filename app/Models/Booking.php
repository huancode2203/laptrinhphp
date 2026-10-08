<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['reference', 'request_key', 'user_id', 'court_id', 'starts_at', 'ends_at', 'status', 'court_amount', 'service_amount', 'total_amount', 'paid_amount', 'notes', 'checked_in_at', 'discount_amount', 'coupon_id', 'coupon_code', 'booking_type', 'series_key'])]
class Booking extends Model
{
    protected $attributes = ['status' => 'confirmed', 'service_amount' => 0, 'paid_amount' => 0, 'discount_amount' => 0, 'booking_type' => 'single'];

    use HasFactory;

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'checked_in_at' => 'immutable_datetime'];
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'booking_services')->withPivot(['name', 'quantity', 'unit_price', 'amount']);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['request_key', 'order_id', 'user_id', 'booking_id', 'purpose', 'partner_code', 'amount', 'order_info', 'extra_data', 'status', 'pay_url', 'trans_id', 'expires_at', 'result_code'])]
class PaymentAttempt extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['amount' => 'integer', 'expires_at' => 'immutable_datetime', 'result_code' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}

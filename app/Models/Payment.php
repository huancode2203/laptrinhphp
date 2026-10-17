<?php

namespace App\Models;

use App\PaymentAllocation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['booking_id', 'received_by', 'request_key', 'kind', 'method', 'amount', 'notes'])]
class Payment extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['allocation' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            $payment->allocation = app(PaymentAllocation::class)->capture(Booking::findOrFail($payment->booking_id), (int) $payment->amount, $payment->kind);
        });
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}

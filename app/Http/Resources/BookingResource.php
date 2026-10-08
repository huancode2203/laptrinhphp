<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'user_id' => $this->user_id,
            'customer' => $this->whenLoaded('user', fn () => ['name' => $this->user->name, 'phone' => $this->user->phone]),
            'court_id' => $this->court_id,
            'court' => $this->whenLoaded('court'),
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at->toIso8601String(),
            'status' => $this->status,
            'court_amount' => $this->court_amount,
            'service_amount' => $this->service_amount,
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'remaining_amount' => max(0, $this->total_amount - $this->paid_amount),
            'refund_pending' => $this->status === 'cancelled' && $this->paid_amount > 0,
            'notes' => $this->notes,
            'services' => $this->whenLoaded('services', fn () => $this->services->map(fn ($service) => ['id' => $service->id, 'name' => $service->pivot->name, 'quantity' => $service->pivot->quantity, 'unit_price' => $service->pivot->unit_price, 'amount' => $service->pivot->amount])),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($payment) => ['id' => $payment->id, 'kind' => $payment->kind, 'amount' => $payment->amount, 'method' => $payment->method, 'created_at' => $payment->created_at->toIso8601String()])),
        ];
    }
}

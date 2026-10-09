<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'district' => $this->district,
            'address' => $this->address,
            'phone' => $this->phone,
            'amenities' => $this->amenities,
            'active' => $this->active, 'image_path' => $this->image_path,
            'opens_minute' => $this->opens_minute, 'closes_minute' => $this->closes_minute, 'peak_minute' => $this->peak_minute,
            'booking_days' => $this->booking_days, 'deposit_percent' => $this->deposit_percent, 'payment_minutes' => $this->payment_minutes, 'fixed_discount' => $this->fixed_discount,
            'courts_count' => $this->whenCounted('courts'),
            'courts' => $this->whenLoaded('courts'),
            'services' => $this->whenLoaded('services'),
        ];
    }
}

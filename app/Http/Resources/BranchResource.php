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
            'active' => $this->active,
            'courts_count' => $this->whenCounted('courts'),
            'courts' => $this->whenLoaded('courts'),
            'services' => $this->whenLoaded('services'),
        ];
    }
}

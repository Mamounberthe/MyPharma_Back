<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PharmacyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'location' => [
                'latitude' => (float) $this->latitude,
                'longitude' => (float) $this->longitude,
            ],
            'phone' => $this->phone,
            'rating' => (float) $this->rating,
            'delivery_available' => $this->delivery_available,
            'distance' => $this->when(isset($this->distance), $this->distance),
            'reviews_count' => $this->when($this->reviews_count, $this->reviews_count),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relations conditionnelles
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
            'stocks' => $this->whenLoaded('stocks'),
        ];
    }
}

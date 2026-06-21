<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
            'description' => $this->description,
            'image_url' => $this->image_url,
            'category' => [
                'id' => $this->category->id,
                'name' => $this->category->name
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relations conditionnelles
            'stocks' => $this->whenLoaded('stocks'),
            'available_pharmacies' => $this->when(
                isset($this->available_pharmacies), 
                $this->available_pharmacies
            ),
            'total_pharmacies' => $this->when(
                isset($this->total_pharmacies), 
                $this->total_pharmacies
            ),
            'price_range' => $this->when(
                isset($this->min_price) && isset($this->max_price), 
                [
                    'min' => (float) $this->min_price,
                    'max' => (float) $this->max_price
                ]
            )
        ];
    }
}

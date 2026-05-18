<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
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
            'rating' => $this->rating,
            'comment' => $this->comment,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relations
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name
            ],
            'pharmacy' => [
                'id' => $this->pharmacy->id,
                'name' => $this->pharmacy->name,
                'rating' => (float) $this->pharmacy->rating
            ]
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'email' => $this->email,
            'role' => $this->role,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Métadonnées
            'is_admin' => $this->isAdmin(),
            'is_client' => $this->isClient(),
            'is_livreur' => $this->isLivreur(),
            
            // Relations conditionnelles
            'orders_count' => $this->when($this->orders_count, $this->orders_count),
            'reviews_count' => $this->when($this->reviews_count, $this->reviews_count)
        ];
    }
}

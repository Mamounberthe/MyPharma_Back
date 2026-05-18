<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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
            'status' => $this->status,
            'total_price' => (float) $this->total_price,
            'delivery_address' => $this->delivery_address,
            'delivered_at' => $this->delivered_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relations
            'pharmacy' => [
                'id' => $this->pharmacy->id,
                'name' => $this->pharmacy->name,
                'address' => $this->pharmacy->address,
                'phone' => $this->pharmacy->phone
            ],
            'items' => $this->whenLoaded('orderItems', function () {
                return $this->orderItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product' => [
                            'id' => $item->product->id,
                            'name' => $item->product->name,
                            'category' => $item->product->category->name
                        ],
                        'quantity' => $item->quantity,
                        'unit_price' => (float) $item->price,
                        'subtotal' => (float) ($item->quantity * $item->price)
                    ];
                });
            }),
            
            // Métadonnées
            'items_count' => $this->when($this->orderItems_count, $this->orderItems_count),
            'can_cancel' => in_array($this->status, ['pending', 'confirmed']),
            'can_review' => $this->status === 'delivered'
        ];
    }
}

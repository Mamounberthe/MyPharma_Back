<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'pharmacy_id' => 'required|integer|exists:pharmacies,id',
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:0',
            'price' => 'required|numeric|min:0',
        ];
    }
}

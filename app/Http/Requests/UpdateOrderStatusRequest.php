<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Seuls les livreurs et admins peuvent modifier le statut
        return $this->user()->isLivreur() || $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:confirmed,delivering,delivered,cancelled'
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status is required',
            'status.in' => 'Status must be one of: confirmed, delivering, delivered, cancelled'
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GetStocksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'pharmacy_id' => 'required|integer|exists:pharmacies,id',
        ];
    }
}

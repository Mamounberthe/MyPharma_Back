<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GetPharmacyReviewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'per_page' => 'nullable|numeric|min:1|max:50'
        ];
    }

    public function messages(): array
    {
        return [
            'per_page.numeric' => 'Per page must be a number',
            'per_page.min' => 'Per page must be at least 1',
            'per_page.max' => 'Per page cannot exceed 50'
        ];
    }
}

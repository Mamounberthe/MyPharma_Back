<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchProductsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Tout le monde peut rechercher
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'query' => 'required|string|min:2|max:100',
            'latitude' => 'sometimes|numeric|between:-90,90',
            'longitude' => 'sometimes|numeric|between:-180,180',
            'radius' => 'sometimes|numeric|min:1|max:100',
            'min_rating' => 'sometimes|numeric|min:0|max:5',
            'min_price' => 'sometimes|numeric|min:0',
            'max_price' => 'sometimes|numeric|min:0',
            'delivery_available' => 'sometimes|boolean',
            'per_page' => 'sometimes|integer|min:1|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'query.required' => 'Search query is required',
            'query.min' => 'Search query must be at least 2 characters',
            'query.max' => 'Search query cannot exceed 100 characters',
            'latitude.numeric' => 'Latitude must be a number',
            'latitude.between' => 'Latitude must be between -90 and 90',
            'longitude.numeric' => 'Longitude must be a number',
            'longitude.between' => 'Longitude must be between -180 and 180',
            'radius.numeric' => 'Search radius must be a number',
            'radius.min' => 'Search radius must be at least 1 km',
            'radius.max' => 'Search radius cannot exceed 100 km',
            'min_rating.numeric' => 'Minimum rating must be a number',
            'min_rating.min' => 'Minimum rating cannot be less than 0',
            'min_rating.max' => 'Minimum rating cannot exceed 5',
            'min_price.numeric' => 'Minimum price must be a number',
            'min_price.min' => 'Minimum price cannot be negative',
            'max_price.numeric' => 'Maximum price must be a number',
            'max_price.min' => 'Maximum price cannot be negative',
            'delivery_available.boolean' => 'Delivery availability must be true or false',
            'per_page.integer' => 'Items per page must be a number',
            'per_page.min' => 'Items per page must be at least 1',
            'per_page.max' => 'Items per page cannot exceed 50',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Vérifier que max_price >= min_price
            if ($this->min_price && $this->max_price) {
                if ($this->max_price < $this->min_price) {
                    $validator->errors()->add('max_price', 'Maximum price cannot be less than minimum price');
                }
            }

            // Vérifier que si latitude est fournie, longitude doit l'être aussi
            if ($this->latitude && !$this->longitude) {
                $validator->errors()->add('longitude', 'Longitude is required when latitude is provided');
            }

            if ($this->longitude && !$this->latitude) {
                $validator->errors()->add('latitude', 'Latitude is required when longitude is provided');
            }
        });
    }
}

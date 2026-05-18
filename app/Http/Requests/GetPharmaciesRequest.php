<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GetPharmaciesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'radius' => 'nullable|numeric|min:1|max:100',
            'min_rating' => 'nullable|numeric|between:1,5',
            'delivery_available' => 'nullable|boolean',
            'is_on_call' => 'nullable|boolean',
            'include_external' => 'nullable|boolean',
            'sort_by' => 'nullable|in:rating,name,created_at',
            'sort_order' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|numeric|min:1|max:50'
        ];
    }

    /**
     * Préparer les données pour la validation.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];
        
        if ($this->has('include_external')) {
            $merge['include_external'] = filter_var($this->include_external, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }
        
        if ($this->has('is_on_call')) {
            $merge['is_on_call'] = filter_var($this->is_on_call, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }
        
        if ($this->has('delivery_available')) {
            $merge['delivery_available'] = filter_var($this->delivery_available, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }
        
        $this->merge($merge);
    }


    public function messages(): array
    {
        return [
            'latitude.numeric' => 'Latitude must be a number',
            'latitude.between' => 'Latitude must be between -90 and 90',
            'longitude.numeric' => 'Longitude must be a number',
            'longitude.between' => 'Longitude must be between -180 and 180',
            'radius.numeric' => 'Radius must be a number',
            'radius.min' => 'Radius must be at least 1 km',
            'radius.max' => 'Radius cannot exceed 100 km',
            'min_rating.numeric' => 'Minimum rating must be a number',
            'min_rating.between' => 'Rating must be between 1 and 5',
            'sort_by.in' => 'Sort by must be one of: rating, name, created_at',
            'sort_order.in' => 'Sort order must be asc or desc',
            'per_page.numeric' => 'Per page must be a number',
            'per_page.min' => 'Per page must be at least 1',
            'per_page.max' => 'Per page cannot exceed 50'
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Seuls les admins et gérants de pharmacie peuvent créer des produits
        return $this->user()->role === 'admin' || $this->user()->role === 'pharmacy_manager';
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'category_id' => 'required|exists:categories,id',
            'barcode' => 'nullable|string|max:50|unique:products,barcode',
            'manufacturer' => 'nullable|string|max:255',
            'requires_prescription' => 'boolean',
            'active' => 'boolean',
            'image_url' => 'nullable|url|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Product name is required',
            'name.max' => 'Product name cannot exceed 255 characters',
            'description.max' => 'Description cannot exceed 2000 characters',
            'category_id.required' => 'Category is required',
            'category_id.exists' => 'Selected category does not exist',
            'barcode.unique' => 'This barcode already exists',
            'manufacturer.max' => 'Manufacturer name cannot exceed 255 characters',
            'image_url.url' => 'Image URL must be a valid URL',
            'image_url.max' => 'Image URL cannot exceed 500 characters',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Vérifier le format du code-barres si fourni
            if ($this->barcode) {
                if (!$this->isValidBarcode($this->barcode)) {
                    $validator->errors()->add('barcode', 'Invalid barcode format');
                }
            }
        });
    }

    /**
     * Valider le format du code-barres
     */
    private function isValidBarcode(string $barcode): bool
    {
        // Accepter EAN-13, UPC-A, et codes personnalisés
        return (strlen($barcode) >= 8 && strlen($barcode) <= 13) && 
               ctype_digit($barcode);
    }
}

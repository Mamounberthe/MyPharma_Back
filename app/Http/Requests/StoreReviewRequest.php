<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'rating' => 'required|integer|between:1,5',
            'comment' => 'nullable|string|max:1000'
        ];
    }

    public function messages(): array
    {
        return [
            'pharmacy_id.required' => 'Pharmacy selection is required',
            'pharmacy_id.exists' => 'Selected pharmacy does not exist',
            'rating.required' => 'Rating is required',
            'rating.integer' => 'Rating must be a number',
            'rating.between' => 'Rating must be between 1 and 5',
            'comment.string' => 'Comment must be text',
            'comment.max' => 'Comment cannot exceed 1000 characters'
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Vérifier que l'utilisateur n'a pas déjà noté cette pharmacie
            if ($this->user()) {
                $existingReview = \App\Models\Review::where('user_id', $this->user()->id)
                    ->where('pharmacy_id', $this->pharmacy_id)
                    ->first();

                if ($existingReview) {
                    $validator->errors()->add('pharmacy_id', 'You have already reviewed this pharmacy');
                }

                // Vérifier que l'utilisateur a au moins une commande livrée
                $hasDeliveredOrder = $this->user()->orders()
                    ->where('pharmacy_id', $this->pharmacy_id)
                    ->where('status', 'delivered')
                    ->exists();

                if (!$hasDeliveredOrder) {
                    $validator->errors()->add('pharmacy_id', 'You must have at least one delivered order to review this pharmacy');
                }
            }
        });
    }
}

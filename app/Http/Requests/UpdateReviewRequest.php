<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReviewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // L'utilisateur ne peut modifier que ses propres avis
        return $this->user()->id === $this->review->user_id;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'rating' => 'sometimes|required|integer|between:1,5',
            'comment' => 'nullable|string|max:1000'
        ];
    }

    public function messages(): array
    {
        return [
            'rating.required' => 'Rating is required',
            'rating.integer' => 'Rating must be a number',
            'rating.between' => 'Rating must be between 1 and 5',
            'comment.string' => 'Comment must be text',
            'comment.max' => 'Comment cannot exceed 1000 characters'
        ];
    }
}

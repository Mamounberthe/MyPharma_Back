<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReviewController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'rating' => 'required|integer|between:1,5',
            'comment' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Vérification que l'utilisateur n'a pas déjà noté cette pharmacie
        $existingReview = Review::where('user_id', $request->user()->id)
            ->where('pharmacy_id', $request->pharmacy_id)
            ->first();

        if ($existingReview) {
            return response()->json([
                'error' => 'You have already reviewed this pharmacy',
                'review' => $existingReview
            ], 422);
        }

        // Vérification que l'utilisateur a au moins une commande validée chez cette pharmacie
        $hasOrder = $request->user()->orders()
            ->where('pharmacy_id', $request->pharmacy_id)
            ->whereIn('status', ['delivered'])
            ->exists();

        if (!$hasOrder) {
            return response()->json([
                'error' => 'You must have at least one delivered order to review this pharmacy'
            ], 422);
        }

        $review = Review::create([
            'user_id' => $request->user()->id,
            'pharmacy_id' => $request->pharmacy_id,
            'rating' => $request->rating,
            'comment' => $request->comment
        ]);

        return response()->json([
            'message' => 'Review created successfully',
            'review' => $review->load(['user:id,name', 'pharmacy:id,name,rating'])
        ], 201);
    }

    public function update($id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'rating' => 'sometimes|integer|between:1,5',
            'comment' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $review = Review::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $review->update($request->only(['rating', 'comment']));

        return response()->json([
            'message' => 'Review updated successfully',
            'review' => $review->load(['user:id,name', 'pharmacy:id,name,rating'])
        ]);
    }

    public function destroy($id, Request $request)
    {
        $review = Review::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $review->delete();

        return response()->json([
            'message' => 'Review deleted successfully'
        ]);
    }
}

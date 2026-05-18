<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReviewRequest;
use App\Http\Requests\UpdateReviewRequest;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewControllerRefactored extends Controller
{
    protected ReviewService $reviewService;

    public function __construct(ReviewService $reviewService)
    {
        $this->reviewService = $reviewService;
    }

    /**
     * Créer un nouvel avis
     */
    public function store(StoreReviewRequest $request): JsonResponse
    {
        try {
            $review = $this->reviewService->createReview(
                $request->validated(),
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Review created successfully',
                'data' => $review
            ], 201);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create review',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Lister les avis d'une pharmacie
     */
    public function pharmacyReviews(Request $request, int $pharmacyId): JsonResponse
    {
        try {
            $filters = $request->only(['rating', 'per_page']);
            $reviews = $this->reviewService->getPharmacyReviews($pharmacyId, $filters);

            return response()->json([
                'success' => true,
                'data' => $reviews
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch reviews',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Lister les avis de l'utilisateur connecté
     */
    public function userReviews(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['rating', 'per_page']);
            $reviews = $this->reviewService->getUserReviews($request->user()->id, $filters);

            return response()->json([
                'success' => true,
                'data' => $reviews
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user reviews',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Mettre à jour un avis
     */
    public function update(UpdateReviewRequest $request, Review $review): JsonResponse
    {
        try {
            $updatedReview = $this->reviewService->updateReview(
                $review,
                $request->validated()
            );

            return response()->json([
                'success' => true,
                'message' => 'Review updated successfully',
                'data' => $updatedReview
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update review',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Supprimer un avis
     */
    public function destroy(Review $review): JsonResponse
    {
        try {
            // Vérifier que l'utilisateur est bien le propriétaire
            if ($review->user_id !== request()->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $deleted = $this->reviewService->deleteReview($review);

            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'Review deleted successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete review'
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete review',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Obtenir les statistiques des avis d'une pharmacie
     */
    public function pharmacyStats(int $pharmacyId): JsonResponse
    {
        try {
            $stats = $this->reviewService->getPharmacyReviewStats($pharmacyId);

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch review stats',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }
}

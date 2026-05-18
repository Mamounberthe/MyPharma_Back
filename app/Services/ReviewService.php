<?php

namespace App\Services;

use App\Models\Pharmacy;
use App\Models\Review;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class ReviewService
{
    /**
     * Créer un nouvel avis
     */
    public function createReview(array $reviewData, int $userId): Review
    {
        // Vérifier si l'utilisateur a déjà noté cette pharmacie
        $this->validateUniqueReview($reviewData['pharmacy_id'], $userId);

        // Créer l'avis
        $review = Review::create([
            'user_id' => $userId,
            'pharmacy_id' => $reviewData['pharmacy_id'],
            'rating' => $reviewData['rating'],
            'comment' => $reviewData['comment'] ?? null,
        ]);

        // Mettre à jour la note moyenne de la pharmacie
        $this->updatePharmacyRating($reviewData['pharmacy_id']);

        return $review->load(['user', 'pharmacy']);
    }

    /**
     * Lister les avis d'une pharmacie
     */
    public function getPharmacyReviews(int $pharmacyId, array $filters = []): LengthAwarePaginator
    {
        $query = Review::with(['user'])
            ->where('pharmacy_id', $pharmacyId)
            ->orderBy('created_at', 'desc');

        // Filtre par note
        if (isset($filters['rating'])) {
            $query->where('rating', $filters['rating']);
        }

        // Pagination
        $perPage = $filters['per_page'] ?? 15;

        return $query->paginate($perPage);
    }

    /**
     * Lister les avis d'un utilisateur
     */
    public function getUserReviews(int $userId, array $filters = []): LengthAwarePaginator
    {
        $query = Review::with(['pharmacy'])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc');

        // Filtre par note
        if (isset($filters['rating'])) {
            $query->where('rating', $filters['rating']);
        }

        // Pagination
        $perPage = $filters['per_page'] ?? 15;

        return $query->paginate($perPage);
    }

    /**
     * Mettre à jour un avis
     */
    public function updateReview(Review $review, array $updateData): Review
    {
        $review->update([
            'rating' => $updateData['rating'] ?? $review->rating,
            'comment' => $updateData['comment'] ?? $review->comment,
        ]);

        // Mettre à jour la note moyenne de la pharmacie
        $this->updatePharmacyRating($review->pharmacy_id);

        return $review->fresh(['user', 'pharmacy']);
    }

    /**
     * Supprimer un avis
     */
    public function deleteReview(Review $review): bool
    {
        $pharmacyId = $review->pharmacy_id;
        
        $deleted = $review->delete();

        if ($deleted) {
            // Mettre à jour la note moyenne de la pharmacie
            $this->updatePharmacyRating($pharmacyId);
        }

        return $deleted;
    }

    /**
     * Valider l'unicité de l'avis
     */
    private function validateUniqueReview(int $pharmacyId, int $userId): void
    {
        $existingReview = Review::where('user_id', $userId)
            ->where('pharmacy_id', $pharmacyId)
            ->first();

        if ($existingReview) {
            throw new \InvalidArgumentException('Vous avez déjà donné un avis pour cette pharmacie.');
        }
    }

    /**
     * Mettre à jour la note moyenne de la pharmacie
     */
    private function updatePharmacyRating(int $pharmacyId): void
    {
        $pharmacy = Pharmacy::findOrFail($pharmacyId);
        
        $averageRating = Review::where('pharmacy_id', $pharmacyId)
            ->avg('rating');

        $pharmacy->update([
            'rating' => round($averageRating, 2)
        ]);
    }

    /**
     * Obtenir les statistiques des avis d'une pharmacie
     */
    public function getPharmacyReviewStats(int $pharmacyId): array
    {
        $reviews = Review::where('pharmacy_id', $pharmacyId);

        return [
            'total_reviews' => $reviews->count(),
            'average_rating' => round($reviews->avg('rating'), 2),
            'rating_distribution' => $reviews
                ->selectRaw('rating, COUNT(*) as count')
                ->groupBy('rating')
                ->orderBy('rating', 'desc')
                ->pluck('count', 'rating')
                ->toArray(),
        ];
    }
}

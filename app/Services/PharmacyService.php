<?php

namespace App\Services;

use App\Models\Pharmacy;
use App\Helpers\GeoHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class PharmacyService
{
    private ExternalPharmacyService $externalService;

    public function __construct(ExternalPharmacyService $externalService)
    {
        $this->externalService = $externalService;
    }

    /**
     * Récupérer les pharmacies avec filtres
     */
    public function getPharmacies(array $filters = [])
    {
        // Si on demande des données externes et qu'on a les coordonnées
        if (isset($filters['include_external']) && $filters['include_external'] && isset($filters['latitude']) && isset($filters['longitude'])) {
            return $this->externalService->getMergedPharmacies(
                $filters['latitude'],
                $filters['longitude'],
                ($filters['radius'] ?? 5) * 1000
            );
        }

        $query = Pharmacy::query();

        // Charger les reviews seulement si nécessaire
        if (!isset($filters['without_reviews'])) {
            $query->with(['reviews' => function($query) {
                $query->latest()->limit(5);
            }]);
        }

        // Filtre par distance (seulement si coordonnées fournies)
        if (isset($filters['latitude']) && isset($filters['longitude'])) {
            $lat = $filters['latitude'];
            $lng = $filters['longitude'];
            $radius = $filters['radius'] ?? 10;

            // Formule de Haversine (distance en km).
            // On filtre via whereRaw (et non having sur un alias) : having sans
            // group by est rejeté par PostgreSQL ET SQLite. whereRaw fonctionne
            // de manière identique sur Postgres (prod), MySQL et SQLite (tests).
            $haversine = '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) '
                . '* cos(radians(longitude) - radians(?)) '
                . '+ sin(radians(?)) * sin(radians(latitude))))';

            $query->selectRaw("*, {$haversine} AS distance", [$lat, $lng, $lat])
                ->whereRaw("{$haversine} <= ?", [$lat, $lng, $lat, $radius])
                ->orderBy('distance');
        }

        // Filtre par note minimale
        if (isset($filters['min_rating'])) {
            $query->where('rating', '>=', $filters['min_rating']);
        }

        // Filtre par disponibilité livraison
        if (isset($filters['delivery_available']) && $filters['delivery_available'] !== null) {
            $query->where('delivery_available', $filters['delivery_available']);
        }

        // Filtre par pharmacie de garde
        if (isset($filters['is_on_call']) && $filters['is_on_call'] !== null) {
            $query->where('is_on_call', $filters['is_on_call']);
        }

        // Tri
        $sortBy = $filters['sort_by'] ?? 'rating';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        if (in_array($sortBy, ['rating', 'name', 'created_at'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Récupérer une pharmacie par ID avec ses relations
     */
    public function getPharmacyById(int $id): Pharmacy
    {
        return Pharmacy::with(['reviews.user', 'stocks.product.category'])
            ->findOrFail($id);
    }

    /**
     * Récupérer les avis d'une pharmacie
     */
    public function getPharmacyReviews(int $id, int $perPage = 10): LengthAwarePaginator
    {
        $pharmacy = Pharmacy::findOrFail($id);

        return $pharmacy->reviews()
            ->with('user:id,name')
            ->latest()
            ->paginate($perPage);
    }

}

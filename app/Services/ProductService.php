<?php

namespace App\Services;

use App\Helpers\GeoHelper;
use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductService
{
    /**
     * Lister les produits avec filtres et pagination
     */
    public function getProducts(array $filters = []): LengthAwarePaginator
    {
        // Version simplifiée pour éviter le timeout
        $query = Product::query();

        // Filtre par catégorie
        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        // Filtre par recherche
        if (isset($filters['search'])) {
            $query->where('name', 'LIKE', '%' . $filters['search'] . '%');
        }

        // Pagination
        $perPage = $filters['per_page'] ?? 15;

        return $query->paginate($perPage);
    }

    /**
     * Obtenir un produit spécifique avec ses stocks
     */
    public function getProduct(int $id): Product
    {
        return Product::findOrFail($id);
    }

    /**
     * Recherche avancée de produits avec filtres géographiques
     */
    public function searchProducts(array $searchParams): array
    {
        $query = $searchParams['query'];
        $latitude = $searchParams['latitude'] ?? null;
        $longitude = $searchParams['longitude'] ?? null;
        $radius = $searchParams['radius'] ?? 10;
        $minRating = $searchParams['min_rating'] ?? null;
        $minPrice = $searchParams['min_price'] ?? null;
        $maxPrice = $searchParams['max_price'] ?? null;
        $deliveryOnly = $searchParams['delivery_available'] ?? null;
        $perPage = $searchParams['per_page'] ?? 15;

        // Requête de base
        $productsQuery = Product::where('name', 'LIKE', "%{$query}%")
            ->with(['category', 'stocks.pharmacy'])
            ->whereHas('stocks', function ($stockQuery) use ($minPrice, $maxPrice) {
                $stockQuery->where('quantity', '>', 0);

                if ($minPrice !== null) {
                    $stockQuery->where('price', '>=', $minPrice);
                }
                if ($maxPrice !== null) {
                    $stockQuery->where('price', '<=', $maxPrice);
                }
            });

        // Pagination
        $paginatedProducts = $productsQuery->paginate($perPage);

        // Traitement des résultats
        $results = collect($paginatedProducts->items())->map(function ($product) use (
            $latitude, $longitude, $radius, $minRating, $deliveryOnly
        ) {
            $availablePharmacies = $this->filterAvailablePharmacies(
                $product->stocks,
                $latitude,
                $longitude,
                $radius,
                $minRating,
                $deliveryOnly
            );

            return [
                'product' => $product,
                'available_pharmacies' => $availablePharmacies,
                'total_pharmacies' => $availablePharmacies->count(),
                'min_price' => $availablePharmacies->pluck('stock.price')->min(),
                'max_price' => $availablePharmacies->pluck('stock.price')->max(),
            ];
        })->filter(fn($result) => $result['total_pharmacies'] > 0)->values();

        return [
            'query' => $query,
            'results' => $results,
            'total_results' => $results->count(),
            'pagination' => [
                'current_page' => $paginatedProducts->currentPage(),
                'last_page' => $paginatedProducts->lastPage(),
                'per_page' => $paginatedProducts->perPage(),
                'total' => $paginatedProducts->total(),
            ],
        ];
    }

    /**
     * Filtrer les pharmacies disponibles selon les critères
     */
    private function filterAvailablePharmacies(
        $stocks,
        ?float $latitude,
        ?float $longitude,
        float $radius,
        ?float $minRating,
        ?bool $deliveryOnly
    ) {
        $availablePharmacies = $stocks
            ->filter(fn($stock) => $stock->quantity > 0)
            ->map(function ($stock) use ($latitude, $longitude) {
                $pharmacy = $stock->pharmacy;

                // Calcul de la distance
                if ($latitude && $longitude) {
                    $pharmacy->distance = GeoHelper::calculateDistance(
                        $latitude,
                        $longitude,
                        $pharmacy->latitude,
                        $pharmacy->longitude
                    );
                }

                return [
                    'pharmacy' => $pharmacy,
                    'stock' => [
                        'quantity' => $stock->quantity,
                        'price' => $stock->price,
                    ],
                ];
            });

        // Filtre par note
        if ($minRating !== null) {
            $availablePharmacies = $availablePharmacies->filter(
                fn($item) => $item['pharmacy']->rating >= $minRating
            );
        }

        // Filtre par livraison
        if ($deliveryOnly) {
            $availablePharmacies = $availablePharmacies->filter(
                fn($item) => $item['pharmacy']->delivery_available
            );
        }

        // Filtre par distance
        if ($latitude && $longitude) {
            $availablePharmacies = $availablePharmacies
                ->filter(fn($item) => ($item['pharmacy']->distance ?? 0) <= $radius)
                ->sortBy('pharmacy.distance');
        }

        return $availablePharmacies->values();
    }

    /**
     * Obtenir les produits par pharmacie
     */
    public function getProductsByPharmacy(int $pharmacyId, array $filters = []): LengthAwarePaginator
    {
        $query = Product::with(['category'])
            ->whereHas('stocks', function ($stockQuery) use ($pharmacyId) {
                $stockQuery->where('pharmacy_id', $pharmacyId)
                    ->where('quantity', '>', 0);
            });

        // Filtre par catégorie
        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        // Filtre par recherche
        if (isset($filters['search'])) {
            $query->where('name', 'LIKE', '%' . $filters['search'] . '%');
        }

        // Filtre par prix
        if (isset($filters['min_price'])) {
            $query->whereHas('stocks', function ($stockQuery) use ($pharmacyId, $filters) {
                $stockQuery->where('pharmacy_id', $pharmacyId)
                    ->where('price', '>=', $filters['min_price']);
            });
        }

        if (isset($filters['max_price'])) {
            $query->whereHas('stocks', function ($stockQuery) use ($pharmacyId, $filters) {
                $stockQuery->where('pharmacy_id', $pharmacyId)
                    ->where('price', '<=', $filters['max_price']);
            });
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Obtenir les statistiques des produits
     */
    public function getProductStats(): array
    {
        return [
            'total_products' => Product::count(),
            'total_categories' => \App\Models\Category::count(),
            'products_with_stock' => Product::whereHas('stocks', function ($query) {
                $query->where('quantity', '>', 0);
            })->count(),
            'avg_price' => \App\Models\Stock::where('quantity', '>', 0)->avg('price'),
        ];
    }

    /**
     * Obtenir les produits les plus populaires
     */
    public function getPopularProducts(int $limit = 10): array
    {
        return Product::with(['category'])
            ->withCount(['stocks' => function ($query) {
                $query->where('quantity', '>', 0);
            }])
            ->whereHas('stocks', function ($query) {
                $query->where('quantity', '>', 0);
            })
            ->orderBy('stocks_count', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}

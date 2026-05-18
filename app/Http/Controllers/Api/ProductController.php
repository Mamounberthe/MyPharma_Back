<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\GeoHelper;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Lister les produits avec filtres et pagination
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'category_id' => 'sometimes|integer|exists:categories,id',
            'search'      => 'sometimes|string|max:100',
            'per_page'    => 'sometimes|integer|min:1|max:50',
        ]);

        $query = Product::with('category');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $query->where('name', 'LIKE', '%' . $request->search . '%');
        }

        return response()->json(
            $query->paginate($request->per_page ?? 15)
        );
    }

    /**
     * Afficher un produit spécifique avec ses stocks par pharmacie
     */
    public function show(int $id): JsonResponse
    {
        $product = Product::with(['category', 'stocks.pharmacy'])
            ->findOrFail($id);

        return response()->json($product);
    }

    /**
     * Recherche avancée — produits disponibles avec filtres géographiques
     */
    public function search(Request $request): JsonResponse
    {
        // ✅ Validation complète des paramètres
        $validated = $request->validate([
            'query'              => 'required|string|min:2|max:100',
            'latitude'           => 'sometimes|numeric|between:-90,90',
            'longitude'          => 'sometimes|numeric|between:-180,180',
            'radius'             => 'sometimes|numeric|min:1|max:100',
            'min_rating'         => 'sometimes|numeric|min:0|max:5',
            'min_price'          => 'sometimes|numeric|min:0',
            'max_price'          => 'sometimes|numeric|min:0',
            'delivery_available' => 'sometimes|boolean',
            'per_page'           => 'sometimes|integer|min:1|max:50',
        ]);

        $query        = $validated['query'];
        $latitude     = $validated['latitude'] ?? null;
        $longitude    = $validated['longitude'] ?? null;
        $radius       = $validated['radius'] ?? 10;
        $minRating    = $validated['min_rating'] ?? null;
        $minPrice     = $validated['min_price'] ?? null;
        $maxPrice     = $validated['max_price'] ?? null;
        $deliveryOnly = $validated['delivery_available'] ?? null;
        $perPage      = $validated['per_page'] ?? 15;

        // ✅ Requête optimisée avec filtres en base
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

        // ✅ Pagination sur la collection brute avant traitement
        $paginatedProducts = $productsQuery->paginate($perPage);

        $results = collect($paginatedProducts->items())->map(function ($product) use (
            $latitude, $longitude, $radius, $minRating, $deliveryOnly
        ) {
            $availablePharmacies = $product->stocks
                ->filter(fn($stock) => $stock->quantity > 0)
                ->map(function ($stock) use ($latitude, $longitude) {
                    $pharmacy = $stock->pharmacy;

                    // Calcul distance via GeoHelper si coordonnées fournies
                    if ($latitude && $longitude) {
                        $pharmacy->distance = GeoHelper::calculateDistance(
                            $latitude, $longitude,
                            $pharmacy->latitude, $pharmacy->longitude
                        );
                    }

                    return [
                        'pharmacy' => $pharmacy,
                        'stock'    => [
                            'quantity' => $stock->quantity,
                            'price'    => $stock->price,
                        ],
                    ];
                });

            // Filtres post-requête
            if ($minRating !== null) {
                $availablePharmacies = $availablePharmacies->filter(
                    fn($item) => $item['pharmacy']->rating >= $minRating
                );
            }

            if ($deliveryOnly) {
                $availablePharmacies = $availablePharmacies->filter(
                    fn($item) => $item['pharmacy']->delivery_available
                );
            }

            if ($latitude && $longitude) {
                $availablePharmacies = $availablePharmacies
                    ->filter(fn($item) => ($item['pharmacy']->distance ?? 0) <= $radius)
                    ->sortBy('pharmacy.distance');
            }

            $availablePharmacies = $availablePharmacies->values();

            return [
                'product'              => $product,
                'available_pharmacies' => $availablePharmacies,
                'total_pharmacies'     => $availablePharmacies->count(),
                'min_price'            => $availablePharmacies->pluck('stock.price')->min(),
                'max_price'            => $availablePharmacies->pluck('stock.price')->max(),
            ];
        })->filter(fn($result) => $result['total_pharmacies'] > 0)->values();

        // ✅ Réponse paginée avec métadonnées
        return response()->json([
            'query'         => $query,
            'results'       => $results,
            'total_results' => $results->count(),
            'pagination'    => [
                'current_page' => $paginatedProducts->currentPage(),
                'last_page'    => $paginatedProducts->lastPage(),
                'per_page'     => $paginatedProducts->perPage(),
                'total'        => $paginatedProducts->total(),
            ],
        ]);
    }
}

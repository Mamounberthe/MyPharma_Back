<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\GeoHelper;
use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
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
            'per_page'    => 'sometimes|integer|min:1|max:100',
        ]);

        $query = Product::with('category')
            ->withMin('stocks', 'price')
            ->withCount(['stocks as in_stock_count' => fn ($q) => $q->where('quantity', '>', 0)]);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $products = $query->paginate($request->per_page ?? 15);

        // Format {data, pagination} — cohérent avec PharmacyController et attendu
        // par le frontend (PaginatedResponse<T>). Le paginateur brut de Laravel
        // n'expose pas de clé "pagination", ce qui cassait la pagination côté client.
        return response()->json([
            'data' => $products->items(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page'    => $products->lastPage(),
                'per_page'     => $products->perPage(),
                'total'        => $products->total(),
                'from'         => $products->firstItem(),
                'to'           => $products->lastItem(),
            ],
        ]);
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
            'per_page'           => 'sometimes|integer|min:1|max:100',
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

        // ✅ Requête optimisée avec filtres en base (recherche insensible
        // à la casse et aux accents — voir Product::scopeSearch).
        $productsQuery = Product::search($query)
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

    /**
     * Lister les produits d'une pharmacie avec le prix/stock de cette pharmacie
     */
    public function pharmacyProducts(int $id): JsonResponse
    {
        $products = Product::with([
                'category',
                'stocks' => fn ($q) => $q->where('pharmacy_id', $id),
            ])
            ->whereHas('stocks', function ($q) use ($id) {
                $q->where('pharmacy_id', $id)->where('quantity', '>', 0);
            })
            ->paginate((int) request('per_page', 15));

        return response()->json([
            'data' => $products->items(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page'    => $products->lastPage(),
                'per_page'     => $products->perPage(),
                'total'        => $products->total(),
                'from'         => $products->firstItem(),
                'to'           => $products->lastItem(),
            ],
        ]);
    }

    /**
     * Créer un produit (admin uniquement)
     */
    public function store(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'category_id' => 'required|integer|exists:categories,id',
            'image_url'   => 'nullable|url|max:500',
        ]);

        $product = Product::create($validated);

        return response()->json($product->load('category'), 201);
    }

    /**
     * Mettre à jour un produit (admin uniquement)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:2000',
            'category_id' => 'sometimes|integer|exists:categories,id',
            'image_url'   => 'nullable|url|max:500',
        ]);

        $product->update($validated);

        return response()->json($product->fresh('category'));
    }

    /**
     * Supprimer un produit (admin uniquement)
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        Product::findOrFail($id)->delete();

        return response()->json(['message' => 'Produit supprimé.']);
    }

    /**
     * Statistiques produits (route publique — agrégats non sensibles)
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'total_products'      => Product::count(),
            'total_categories'    => Category::count(),
            'products_with_stock' => Product::whereHas('stocks', fn ($q) => $q->where('quantity', '>', 0))->count(),
            'avg_price'           => round((float) Stock::where('quantity', '>', 0)->avg('price'), 2),
        ]);
    }

    /**
     * Produits les plus disponibles (triés par nombre de pharmacies en stock)
     */
    public function popular(Request $request): JsonResponse
    {
        $limit = min((int) $request->get('limit', 10), 50);

        $products = Product::with('category')
            ->withCount(['stocks as in_stock_count' => fn ($q) => $q->where('quantity', '>', 0)])
            ->whereHas('stocks', fn ($q) => $q->where('quantity', '>', 0))
            ->orderByDesc('in_stock_count')
            ->limit($limit)
            ->get();

        return response()->json($products);
    }
}

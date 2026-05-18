<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchProductsRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductControllerRefactored extends Controller
{
    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    /**
     * Lister les produits avec filtres et pagination
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'category_id',
                'search',
                'per_page'
            ]);

            $products = $this->productService->getProducts($filters);

            return response()->json([
                'success' => true,
                'data' => $products
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch products',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Afficher un produit spécifique avec ses stocks par pharmacie
     */
    public function show(int $id): JsonResponse
    {
        try {
            $product = $this->productService->getProduct($id);

            return response()->json([
                'success' => true,
                'data' => $product
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch product',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Recherche avancée — produits disponibles avec filtres géographiques
     */
    public function search(SearchProductsRequest $request): JsonResponse
    {
        try {
            $searchResults = $this->productService->searchProducts(
                $request->validated()
            );

            return response()->json([
                'success' => true,
                'data' => $searchResults
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Search failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Créer un nouveau produit
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        try {
            $product = Product::create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Product created successfully',
                'data' => $product->load('category')
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create product',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Mettre à jour un produit
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        try {
            $product->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'data' => $product->fresh('category')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update product',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Supprimer un produit
     */
    public function destroy(Product $product): JsonResponse
    {
        try {
            // Vérifier les permissions
            if (!in_array(request()->user()->role, ['admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $deleted = $product->delete();

            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'Product deleted successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product'
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Obtenir les produits d'une pharmacie spécifique
     */
    public function pharmacyProducts(Request $request, int $pharmacyId): JsonResponse
    {
        try {
            $filters = $request->only([
                'category_id',
                'search',
                'min_price',
                'max_price',
                'per_page'
            ]);

            $products = $this->productService->getProductsByPharmacy(
                $pharmacyId,
                $filters
            );

            return response()->json([
                'success' => true,
                'data' => $products
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch pharmacy products',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Obtenir les statistiques des produits
     */
    public function stats(): JsonResponse
    {
        try {
            // Seuls les admins peuvent voir les stats
            if (request()->user()->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $stats = $this->productService->getProductStats();

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch product stats',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Obtenir les produits les plus populaires
     */
    public function popular(Request $request): JsonResponse
    {
        try {
            $limit = min($request->get('limit', 10), 50); // Max 50

            $popularProducts = $this->productService->getPopularProducts($limit);

            return response()->json([
                'success' => true,
                'data' => $popularProducts
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch popular products',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GetPharmaciesRequest;
use App\Http\Requests\GetPharmacyReviewsRequest;
use App\Http\Resources\PharmacyResource;
use App\Http\Resources\ReviewResource;
use App\Models\Pharmacy;
use App\Services\PharmacyService;
use Illuminate\Http\JsonResponse;

class PharmacyController extends Controller
{
    private PharmacyService $pharmacyService;

    public function __construct(PharmacyService $pharmacyService)
    {
        $this->pharmacyService = $pharmacyService;
    }

    /**
     * Lister les pharmacies avec filtres
     */
    public function index(GetPharmaciesRequest $request): JsonResponse
    {
        try {
            $pharmacies = $this->pharmacyService->getPharmacies($request->validated());

            return response()->json([
                'data' => PharmacyResource::collection($pharmacies),
                'pagination' => [
                    'current_page' => $pharmacies->currentPage(),
                    'last_page' => $pharmacies->lastPage(),
                    'per_page' => $pharmacies->perPage(),
                    'total' => $pharmacies->total(),
                    'from' => $pharmacies->firstItem(),
                    'to' => $pharmacies->lastItem()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve pharmacies',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Afficher une pharmacie spécifique
     */
    public function show(int $id): JsonResponse
    {
        try {
            $pharmacy = $this->pharmacyService->getPharmacyById($id);

            return response()->json(new PharmacyResource($pharmacy));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Pharmacy not found',
                'message' => 'The requested pharmacy does not exist'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve pharmacy',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Lister les avis d'une pharmacie
     */
    public function reviews(int $id, GetPharmacyReviewsRequest $request): JsonResponse
    {
        try {
            $perPage = $request->validated()['per_page'] ?? 10;
            $reviews = $this->pharmacyService->getPharmacyReviews($id, $perPage);

            return response()->json([
                'data' => ReviewResource::collection($reviews->items()),
                'pagination' => [
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total(),
                    'from' => $reviews->firstItem(),
                    'to' => $reviews->lastItem()
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Pharmacy not found',
                'message' => 'The requested pharmacy does not exist'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve reviews',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}

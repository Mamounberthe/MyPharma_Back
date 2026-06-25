<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPromotionController extends Controller
{
    /**
     * S'assurer que seul un administrateur peut accéder à ces routes.
     */
    private function ensureAdmin(): void
    {
        $user = request()->user();
        if (!$user || !$user->isAdmin()) {
            abort(403, 'Accès réservé aux administrateurs.');
        }
    }

    /**
     * Lister les promotions (stub)
     */
    public function index(): JsonResponse
    {
        $this->ensureAdmin();

        return response()->json([
            'message' => 'Lister les promotions (stub)',
            'data' => []
        ]);
    }

    /**
     * Créer une promotion (stub)
     */
    public function store(Request $request): JsonResponse
    {
        $this->ensureAdmin();

        return response()->json([
            'message' => 'Promotion créée (stub)',
            'data' => $request->all()
        ], 201);
    }

    /**
     * Afficher une promotion spécifique (stub)
     */
    public function show(int $id): JsonResponse
    {
        $this->ensureAdmin();

        return response()->json([
            'message' => 'Afficher la promotion (stub)',
            'id' => $id
        ]);
    }

    /**
     * Modifier une promotion (stub)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $this->ensureAdmin();

        return response()->json([
            'message' => 'Promotion modifiée (stub)',
            'id' => $id,
            'data' => $request->all()
        ]);
    }

    /**
     * Supprimer une promotion (stub)
     */
    public function destroy(int $id): JsonResponse
    {
        $this->ensureAdmin();

        return response()->json([
            'message' => 'Promotion supprimée (stub)',
            'id' => $id
        ]);
    }
}

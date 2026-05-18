<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderTracking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderTrackingController extends Controller
{
    /**
     * Mettre à jour la position du livreur (uniquement pour le livreur assigné)
     */
    public function updateLocation(Order $order, Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'heading' => 'nullable|integer',
            'speed' => 'nullable|integer',
        ]);

        // Vérifier que l'utilisateur est bien le livreur assigné à cette commande
        if ($order->driver_id !== Auth::id()) {
            return response()->json(['message' => 'Non autorisé. Vous n\'êtes pas le livreur assigné à cette commande.'], 403);
        }

        // Vérifier que la commande est en cours de livraison
        if ($order->status !== 'delivering') {
            return response()->json(['message' => 'Le tracking n\'est possible que pour les commandes en cours de livraison.'], 400);
        }

        $tracking = OrderTracking::create([
            'order_id' => $order->id,
            'driver_id' => Auth::id(),
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'heading' => $request->heading,
            'speed' => $request->speed,
        ]);

        return response()->json($tracking, 201);
    }

    /**
     * Récupérer la dernière position du livreur (pour le client qui a passé la commande)
     */
    public function getLocation(Order $order): JsonResponse
    {
        // Vérifier que l'utilisateur est bien celui qui a passé la commande ou l'admin
        if ($order->user_id !== Auth::id() && Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $tracking = $order->latestTracking;

        if (!$tracking) {
            return response()->json(['message' => 'Aucune donnée de tracking disponible.'], 404);
        }

        return response()->json($tracking);
    }
}

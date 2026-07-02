<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use AuthorizesRequests;

    private OrderService $orderService;

    // ✅ Statuts réels de la BDD dans l'ordre chronologique
    private const TRACKING_STEPS = [
        'pending'    => ['label' => 'Commande confirmée',   'icon' => '✓',  'estimated_minutes' => 0],
        'confirmed'  => ['label' => 'Préparation en cours', 'icon' => '⚙',  'estimated_minutes' => 10],
        'delivering' => ['label' => 'En livraison',         'icon' => '🚗', 'estimated_minutes' => 30],
        'delivered'  => ['label' => 'Livrée',               'icon' => '🏠', 'estimated_minutes' => 45],
    ];

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * Lister les commandes de l'utilisateur
     */
    public function index(Request $request): JsonResponse
    {
        $orders = $this->orderService->getUserOrders(
            $request->user()->id,
            $request->get('per_page', 10),
            $request->get('status')
        );

        return response()->json([
            'data' => OrderResource::collection($orders->items()),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
                'per_page'     => $orders->perPage(),
                'total'        => $orders->total(),
            ],
        ]);
    }

    /**
     * Créer une nouvelle commande
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        try {
            $order = $this->orderService->createOrder(
                $request->validated(),
                $request->user()->id
            );

            return response()->json([
                'message' => 'Commande créée avec succès',
                'order'   => new OrderResource($order)
            ], 201);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error'   => 'Données invalides',
                'message' => $e->getMessage()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Erreur lors de la création de la commande',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher une commande spécifique
     */
    public function show(Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        return response()->json(new OrderResource($order));
    }

    /**
     * Suivi en temps réel d'une commande
     * GET /api/v1/orders/{id}/tracking
     */
    public function tracking(Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $order->load(['pharmacy:id,name,address,phone', 'items.product:id,name']);

        $currentStatus = $order->status;

        // Cas annulé — traitement séparé
        if ($currentStatus === 'cancelled') {
            return response()->json([
                'order_id'       => $order->id,
                'current_status' => 'cancelled',
                'status_label'   => 'Commande annulée',
                'is_terminal'    => true,
                'steps'          => [],
                'pharmacy'       => [
                    'id'      => $order->pharmacy->id,
                    'name'    => $order->pharmacy->name,
                    'address' => $order->pharmacy->address,
                    'phone'   => $order->pharmacy->phone,
                ],
                'items_count'    => $order->items->count(),
                'cancelled_at'   => $order->updated_at->toISOString(),
            ]);
        }

        $steps        = self::TRACKING_STEPS;
        $statusKeys   = array_keys($steps);
        $currentIndex = array_search($currentStatus, $statusKeys);

        // Statut inconnu — sécurité
        if ($currentIndex === false) {
            return response()->json([
                'error'   => 'Statut inconnu',
                'status'  => $currentStatus,
            ], 422);
        }

        // Construire le timeline
        $timeline = collect($steps)->map(function ($step, $statusKey) use ($statusKeys, $currentIndex) {
            $stepIndex = array_search($statusKey, $statusKeys);

            return [
                'status'    => $statusKey,
                'label'     => $step['label'],
                'icon'      => $step['icon'],
                'state'     => $stepIndex < $currentIndex ? 'done'
                    : ($stepIndex === $currentIndex ? 'active' : 'pending'),
                'completed' => $stepIndex <= $currentIndex,
            ];
        })->values();

        // Temps restant estimé
        $totalMinutes   = self::TRACKING_STEPS['delivered']['estimated_minutes'];
        $currentMinutes = self::TRACKING_STEPS[$currentStatus]['estimated_minutes'];
        $minutesLeft    = max(0, $totalMinutes - $currentMinutes);

        return response()->json([
            'order_id'           => $order->id,
            'current_status'     => $currentStatus,
            'status_label'       => $steps[$currentStatus]['label'],
            'is_terminal'        => $currentStatus === 'delivered',
            'estimated_delivery' => $currentStatus !== 'delivered'
                ? now()->addMinutes($minutesLeft)->toISOString()
                : null,
            'minutes_remaining'  => $minutesLeft,
            'steps'              => $timeline,
            'pharmacy'           => [
                'id'      => $order->pharmacy->id,
                'name'    => $order->pharmacy->name,
                'address' => $order->pharmacy->address,
                'phone'   => $order->pharmacy->phone,
            ],
            'items_count'        => $order->items->count(),
            'last_updated'       => $order->updated_at->toISOString(),
        ]);
    }

    /**
     * Mettre à jour le statut d'une commande
     */
    public function updateStatus(Order $order, UpdateOrderStatusRequest $request): JsonResponse
    {
        $this->authorizeStatusUpdate($order, $request->user());

        try {
            $updatedOrder = $this->orderService->updateOrderStatus(
                $order,
                $request->validated()['status']
            );

            return response()->json([
                'message' => 'Statut mis à jour avec succès',
                'order'   => new OrderResource($updatedOrder)
            ]);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error'   => 'Statut invalide',
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Vérifier si l'utilisateur peut mettre à jour le statut
     */
    private function authorizeStatusUpdate(Order $order, $user): void
    {
        if ($user->isClient()) {
            if ($order->user_id !== $user->id || request('status') !== 'cancelled') {
                abort(403, 'Non autorisé');
            }
            return;
        }

        if ($user->isLivreur() || $user->isAdmin()) {
            return;
        }

        abort(403, 'Non autorisé');
    }
}

<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Stock;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(private NotificationService $notificationService) {}

    public function createOrder(array $orderData, int $userId): Order
    {
        return DB::transaction(function () use ($orderData, $userId) {
            $pharmacyId = $orderData['pharmacy_id'];
            $items = $orderData['items'];
            
            // 1. Validation des stocks et calcul du total
            $validatedItems = $this->validateStockAndCalculateTotal($pharmacyId, $items);
            $totalPrice = $this->calculateTotalPrice($validatedItems);
            
            // 2. Création de la commande
            $order = $this->createOrderRecord($userId, $pharmacyId, $totalPrice, $orderData['delivery_address']);
            
            // 3. Création des items et mise à jour des stocks
            $this->createOrderItemsAndUpdateStock($order->id, $validatedItems, $pharmacyId);
            
            return $order->load(['pharmacy', 'orderItems.product']);
        });
    }
    
    /**
     * Valider les stocks et retourner les items validés avec prix
     */
    private function validateStockAndCalculateTotal(int $pharmacyId, array $items): array
    {
        $validatedItems = [];
        
        foreach ($items as $item) {
            $stock = $this->getProductStock($pharmacyId, $item['product_id']);
            
            if (!$stock || $stock->quantity < $item['quantity']) {
                throw new \InvalidArgumentException(
                    "Stock insuffisant pour le produit ID: {$item['product_id']}. " .
                    "Disponible: " . ($stock ? $stock->quantity : 0) . ", Demandé: {$item['quantity']}"
                );
            }
            
            $validatedItems[] = [
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'price' => $stock->price,
                'stock' => $stock
            ];
        }
        
        return $validatedItems;
    }
    
    /**
     * Calculer le prix total de la commande
     */
    private function calculateTotalPrice(array $validatedItems): float
    {
        return array_reduce($validatedItems, function ($total, $item) {
            return $total + ($item['price'] * $item['quantity']);
        }, 0);
    }
    
    /**
     * Créer l'enregistrement de la commande
     */
    private function createOrderRecord(int $userId, int $pharmacyId, float $totalPrice, string $deliveryAddress): Order
    {
        return Order::create([
            'user_id' => $userId,
            'pharmacy_id' => $pharmacyId,
            'total_price' => $totalPrice,
            'status' => 'pending',
            'delivery_address' => $deliveryAddress
        ]);
    }
    
    /**
     * Créer les items de commande et mettre à jour les stocks
     */
    private function createOrderItemsAndUpdateStock(int $orderId, array $validatedItems, int $pharmacyId): void
    {
        foreach ($validatedItems as $item) {
            // Création de l'item de commande
            OrderItem::create([
                'order_id' => $orderId,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'price' => $item['price']
            ]);
            
            // Mise à jour du stock
            $item['stock']->decrement('quantity', $item['quantity']);
        }
    }
    
    /**
     * Récupérer le stock d'un produit dans une pharmacie
     */
    private function getProductStock(int $pharmacyId, int $productId): ?Stock
    {
        return Stock::where('pharmacy_id', $pharmacyId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();
    }
    
    /**
     * Mettre à jour le statut d'une commande
     */
    public function updateOrderStatus(Order $order, string $newStatus): Order
    {
        return DB::transaction(function () use ($order, $newStatus) {
            switch ($newStatus) {
                case 'confirmed':
                    $order->confirm();
                    break;
                case 'delivering':
                    $order->startDelivery();
                    break;
                case 'delivered':
                    $order->deliver();
                    break;
                case 'cancelled':
                    $this->handleOrderCancellation($order);
                    $order->cancel();
                    break;
                default:
                    throw new \InvalidArgumentException("Statut invalide: {$newStatus}");
            }
            
            $fresh = $order->fresh(['user']);
            $this->notificationService->notifyOrderStatusChange($fresh);
            return $fresh;
        });
    }
    
    /**
     * Gérer l'annulation d'une commande (restitution des stocks)
     */
    private function handleOrderCancellation(Order $order): void
    {
        foreach ($order->orderItems as $item) {
            Stock::where('pharmacy_id', $order->pharmacy_id)
                ->where('product_id', $item->product_id)
                ->increment('quantity', $item->quantity);
        }
    }
    
    /**
     * Récupérer les commandes d'un utilisateur
     */
    public function getUserOrders(int $userId, int $perPage = 10, ?string $status = null)
    {
        $query = Order::where('user_id', $userId)
            ->with(['pharmacy', 'orderItems.product', 'orderItems.product.category'])
            ->latest();

        if ($status) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }
}

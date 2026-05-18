<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->with(['pharmacy', 'orderItems.product', 'orderItems.product.category'])
            ->latest()
            ->paginate($request->per_page ?? 10);

        return response()->json($orders);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'delivery_address' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $pharmacyId = $request->pharmacy_id;
            $items = $request->items;
            $totalPrice = 0;
            $orderItems = [];

            // Vérification des stocks et calcul du total
            foreach ($items as $item) {
                $stock = Stock::where('pharmacy_id', $pharmacyId)
                    ->where('product_id', $item['product_id'])
                    ->first();

                if (!$stock || $stock->quantity < $item['quantity']) {
                    DB::rollBack();
                    return response()->json([
                        'error' => 'Insufficient stock for product ID: ' . $item['product_id'],
                        'available_quantity' => $stock->quantity ?? 0
                    ], 422);
                }

                $itemTotal = $stock->price * $item['quantity'];
                $totalPrice += $itemTotal;

                $orderItems[] = [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $stock->price
                ];
            }

            // Création de la commande
            $order = Order::create([
                'user_id' => $request->user()->id,
                'pharmacy_id' => $pharmacyId,
                'total_price' => $totalPrice,
                'status' => 'pending',
                'delivery_address' => $request->delivery_address
            ]);

            // Création des items de commande
            foreach ($orderItems as $orderItem) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $orderItem['product_id'],
                    'quantity' => $orderItem['quantity'],
                    'price' => $orderItem['price']
                ]);

                // Mise à jour du stock
                Stock::where('pharmacy_id', $pharmacyId)
                    ->where('product_id', $orderItem['product_id'])
                    ->decrement('quantity', $orderItem['quantity']);
            }

            DB::commit();

            return response()->json([
                'message' => 'Order created successfully',
                'order' => $order->load(['pharmacy', 'orderItems.product'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to create order',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id, Request $request)
    {
        $order = Order::where('user_id', $request->user()->id)
            ->with(['pharmacy', 'orderItems.product', 'orderItems.product.category'])
            ->findOrFail($id);

        return response()->json($order);
    }

    public function updateStatus($id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:confirmed,delivering,delivered,cancelled'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $order = Order::findOrFail($id);

        // Vérification des permissions
        if ($request->user()->isClient() && $order->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($request->user()->isLivreur() || $request->user()->isAdmin()) {
            $newStatus = $request->status;
            
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
                    // Restitution des stocks en cas d'annulation
                    foreach ($order->orderItems as $item) {
                        Stock::where('pharmacy_id', $order->pharmacy_id)
                            ->where('product_id', $item->product_id)
                            ->increment('quantity', $item->quantity);
                    }
                    $order->cancel();
                    break;
            }

            return response()->json([
                'message' => 'Order status updated successfully',
                'order' => $order->fresh()
            ]);
        }

        return response()->json(['error' => 'Unauthorized'], 403);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Pharmacy;
use App\Models\Product;
use App\Models\Stock;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function __construct(private OrderService $orderService) {}

    private function ensureAdmin(Request $request): void
    {
        if (! $request->user()->isAdmin()) {
            abort(403, 'Accès réservé aux administrateurs.');
        }
    }

    public function stats(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        return response()->json([
            'orders' => [
                'total'      => Order::count(),
                'pending'    => Order::where('status', 'pending')->count(),
                'confirmed'  => Order::where('status', 'confirmed')->count(),
                'delivering' => Order::where('status', 'delivering')->count(),
                'delivered'  => Order::where('status', 'delivered')->count(),
                'cancelled'  => Order::where('status', 'cancelled')->count(),
            ],
            'revenue' => [
                'total'   => (float) Order::where('status', 'delivered')->sum('total_price'),
                'pending' => (float) Order::whereIn('status', ['pending', 'confirmed', 'delivering'])->sum('total_price'),
            ],
            'users' => [
                'total'    => User::count(),
                'clients'  => User::where('role', 'client')->count(),
                'livreurs' => User::where('role', 'livreur')->count(),
                'admins'   => User::where('role', 'admin')->count(),
            ],
            'products'   => ['total' => Product::count()],
            'pharmacies' => ['total' => Pharmacy::count()],
        ]);
    }

    public function orders(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $query = Order::with(['user:id,name,email', 'pharmacy:id,name,phone', 'orderItems'])
            ->latest();

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $orders = $query->paginate($request->get('per_page', 15));

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

    public function updateOrderStatus(Order $order, Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $request->validate([
            'status' => 'required|in:pending,confirmed,delivering,delivered,cancelled',
        ]);

        try {
            $updated = $this->orderService->updateOrderStatus($order, $request->status);
            return response()->json([
                'message' => 'Statut mis à jour.',
                'order'   => new OrderResource($updated),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ---------------------------------------------------------------
    // Pharmacies
    // ---------------------------------------------------------------

    public function pharmacies(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $pharmacies = Pharmacy::orderBy('name')->get(['id', 'name', 'address', 'phone', 'email', 'is_on_call', 'delivery_available']);
        return response()->json($pharmacies);
    }

    public function createPharmacy(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'is_on_call' => 'boolean',
            'delivery_available' => 'boolean',
        ]);

        $pharmacy = Pharmacy::create($data);
        return response()->json(['message' => 'Pharmacie créée.', 'pharmacy' => $pharmacy], 201);
    }

    public function updatePharmacy(Pharmacy $pharmacy, Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'address' => 'sometimes|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'is_on_call' => 'sometimes|boolean',
            'delivery_available' => 'sometimes|boolean',
        ]);

        $pharmacy->update($data);
        return response()->json(['message' => 'Pharmacie mise à jour.', 'pharmacy' => $pharmacy]);
    }

    public function deletePharmacy(Pharmacy $pharmacy, Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $pharmacy->delete();
        return response()->json(['message' => 'Pharmacie supprimée.']);
    }

    // ---------------------------------------------------------------
    // Stocks
    // ---------------------------------------------------------------

    public function stocks(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $request->validate(['pharmacy_id' => 'required|integer|exists:pharmacies,id']);

        $stocks = Stock::where('pharmacy_id', $request->pharmacy_id)
            ->with('product:id,name,image_url')
            ->orderBy('id')
            ->get();

        return response()->json($stocks);
    }

    public function updateStock(Stock $stock, Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'quantity' => 'required|integer|min:0',
            'price'    => 'required|numeric|min:0',
        ]);

        $stock->update($data);
        return response()->json(['message' => 'Stock mis à jour.', 'stock' => $stock->load('product:id,name')]);
    }

    public function createStock(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'pharmacy_id' => 'required|integer|exists:pharmacies,id',
            'product_id'  => 'required|integer|exists:products,id',
            'quantity'    => 'required|integer|min:0',
            'price'       => 'required|numeric|min:0',
        ]);

        if (Stock::where('pharmacy_id', $data['pharmacy_id'])->where('product_id', $data['product_id'])->exists()) {
            return response()->json(['message' => 'Ce produit existe déjà dans cette pharmacie.'], 422);
        }

        $stock = Stock::create($data);
        return response()->json(['message' => 'Stock créé.', 'stock' => $stock->load('product:id,name')], 201);
    }

    public function deleteStock(Stock $stock, Request $request): JsonResponse
    {
        $this->ensureAdmin($request);
        $stock->delete();
        return response()->json(['message' => 'Stock supprimé.']);
    }
}

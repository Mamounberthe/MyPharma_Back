<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePharmacyRequest;
use App\Http\Requests\CreateStockRequest;
use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\GetStocksRequest;
use App\Http\Requests\UpdatePharmacyRequest;
use App\Http\Requests\UpdateStockRequest;
use App\Http\Requests\UpdateUserAdminRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Pharmacy;
use App\Models\Product;
use App\Models\Stock;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private OrderService $orderService) {}

    public function stats(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Stock::class);

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
        $this->authorize('viewAny', Order::class);

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
        $pharmacies = Pharmacy::orderBy('name')->get(['id', 'name', 'address', 'phone', 'email', 'is_on_call', 'delivery_available']);
        return response()->json($pharmacies);
    }

    public function createPharmacy(CreatePharmacyRequest $request): JsonResponse
    {
        $pharmacy = Pharmacy::create($request->validated());
        return response()->json(['message' => 'Pharmacie créée.', 'pharmacy' => $pharmacy], 201);
    }

    public function updatePharmacy(UpdatePharmacyRequest $request, Pharmacy $pharmacy): JsonResponse
    {
        $pharmacy->update($request->validated());
        return response()->json(['message' => 'Pharmacie mise à jour.', 'pharmacy' => $pharmacy]);
    }

    public function deletePharmacy(Pharmacy $pharmacy): JsonResponse
    {
        $this->authorize('delete', $pharmacy);
        $pharmacy->delete();
        return response()->json(['message' => 'Pharmacie supprimée.']);
    }

    // ---------------------------------------------------------------
    // Stocks
    // ---------------------------------------------------------------

    public function stocks(GetStocksRequest $request): JsonResponse
    {
        $stocks = Stock::where('pharmacy_id', $request->pharmacy_id)
            ->with('product:id,name,image_url')
            ->orderBy('id')
            ->get();

        return response()->json($stocks);
    }

    public function updateStock(UpdateStockRequest $request, Stock $stock): JsonResponse
    {
        $stock->update($request->validated());
        return response()->json(['message' => 'Stock mis à jour.', 'stock' => $stock->load('product:id,name')]);
    }

    public function createStock(CreateStockRequest $request): JsonResponse
    {
        if (Stock::where('pharmacy_id', $request->pharmacy_id)->where('product_id', $request->product_id)->exists()) {
            return response()->json(['message' => 'Ce produit existe déjà dans cette pharmacie.'], 422);
        }

        $stock = Stock::create($request->validated());
        return response()->json(['message' => 'Stock créé.', 'stock' => $stock->load('product:id,name')], 201);
    }

    public function deleteStock(Stock $stock): JsonResponse
    {
        $this->authorize('delete', $stock);
        $stock->delete();
        return response()->json(['message' => 'Stock supprimé.']);
    }

    // ---------------------------------------------------------------
    // Utilisateurs
    // ---------------------------------------------------------------

    public function users(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $query = User::query();

        if ($role = $request->get('role')) {
            $query->where('role', $role);
        }

        $users = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $users->items(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function createUser(CreateUserRequest $request): JsonResponse
    {
        $user = new User([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);
        $user->forceFill(['role' => $request->role]);
        $user->save();

        return response()->json(['message' => 'Utilisateur créé.', 'user' => $user->fresh()], 201);
    }

    public function updateUser(UpdateUserAdminRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }
        $user->update($data);
        if (isset($data['role'])) {
            $user->forceFill(['role' => $data['role']])->save();
        }

        return response()->json(['message' => 'Utilisateur mis à jour.', 'user' => $user->fresh()]);
    }

    public function deleteUser(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Vous ne pouvez pas supprimer votre propre compte.'], 422);
        }

        $user->delete();
        return response()->json(['message' => 'Utilisateur supprimé.']);
    }

    // ---------------------------------------------------------------
    // Rapports
    // ---------------------------------------------------------------

    public function reports(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Stock::class);

        $startDate = $request->get('start_date', now()->subMonth()->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        return response()->json([
            'orders' => [
                'total' => Order::whereBetween('created_at', [$startDate, $endDate])->count(),
                'delivered' => Order::whereBetween('created_at', [$startDate, $endDate])->where('status', 'delivered')->count(),
                'cancelled' => Order::whereBetween('created_at', [$startDate, $endDate])->where('status', 'cancelled')->count(),
                'revenue' => Order::whereBetween('created_at', [$startDate, $endDate])->where('status', 'delivered')->sum('total_price'),
            ],
            'top_pharmacies' => Pharmacy::withCount('orders')
                ->whereHas('orders', function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('created_at', [$startDate, $endDate]);
                })
                ->orderByDesc('orders_count')
                ->limit(10)
                ->get(['id', 'name', 'orders_count']),
            'top_products' => Product::withCount('orderItems')
                ->whereHas('orderItems', function ($q) use ($startDate, $endDate) {
                    $q->whereHas('order', function ($orderQ) use ($startDate, $endDate) {
                        $orderQ->whereBetween('created_at', [$startDate, $endDate]);
                    });
                })
                ->orderByDesc('order_items_count')
                ->limit(10)
                ->get(['id', 'name', 'order_items_count']),
        ]);
    }

    // ---------------------------------------------------------------
    // Notifications
    // ---------------------------------------------------------------

    public function notifications(Request $request): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\Notification::class);

        $notifications = \App\Models\Notification::with('user:id,name,email')
            ->latest()
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $notifications->items(),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    public function createNotification(Request $request): JsonResponse
    {
        $this->authorize('create', \App\Models\Notification::class);

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'type' => 'required|string',
            'data' => 'required|array',
        ]);

        $notification = \App\Models\Notification::create([
            'user_id' => $request->user_id,
            'type' => $request->type,
            'data' => $request->data,
        ]);

        return response()->json(['message' => 'Notification créée.', 'notification' => $notification], 201);
    }

    // ---------------------------------------------------------------
    // Paramètres
    // ---------------------------------------------------------------

    public function settings(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Stock::class);

        return response()->json([
            'app' => [
                'name' => config('app.name'),
                'url' => config('app.url'),
                'timezone' => config('app.timezone'),
            ],
            'delivery' => [
                'enabled' => config('services.delivery.enabled', true),
                'base_fee' => config('services.delivery.base_fee', 5.00),
            ],
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Stock::class);

        $request->validate([
            'delivery_enabled' => 'sometimes|boolean',
            'delivery_base_fee' => 'sometimes|numeric|min:0',
        ]);

        if ($request->has('delivery_enabled')) {
            config(['services.delivery.enabled' => $request->delivery_enabled]);
        }
        if ($request->has('delivery_base_fee')) {
            config(['services.delivery.base_fee' => $request->delivery_base_fee]);
        }

        return response()->json(['message' => 'Paramètres mis à jour.']);
    }

    // ---------------------------------------------------------------
    // Journal d'activité
    // ---------------------------------------------------------------

    public function activityLog(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Stock::class);

        $startDate = $request->get('start_date', now()->subDays(7)->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        return response()->json([
            'orders' => Order::with(['user:id,name', 'pharmacy:id,name'])
                ->whereBetween('created_at', [$startDate, $endDate])
                ->latest()
                ->limit(50)
                ->get(['id', 'user_id', 'pharmacy_id', 'status', 'total_price', 'created_at']),
            'users_created' => User::whereBetween('created_at', [$startDate, $endDate])->count(),
            'pharmacies_created' => Pharmacy::whereBetween('created_at', [$startDate, $endDate])->count(),
        ]);
    }
}

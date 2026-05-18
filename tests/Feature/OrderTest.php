<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Pharmacy;
use App\Models\Stock;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer des données de test
        $this->user = User::factory()->create(['role' => 'client']);
        $this->pharmacy = Pharmacy::factory()->create();
        $this->product = Product::factory()->create();
        
        Stock::factory()->create([
            'pharmacy_id' => $this->pharmacy->id,
            'product_id' => $this->product->id,
            'quantity' => 100,
            'price' => 10.99
        ]);
    }

    /**
     * Test order creation.
     */
    public function test_can_create_order(): void
    {
        $token = $this->user->createToken('auth_token')->plainTextToken;

        $orderData = [
            'pharmacy_id' => $this->pharmacy->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2
                ]
            ],
            'delivery_address' => '123 Test Street'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/orders', $orderData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'order' => [
                    'id',
                    'status',
                    'total_price',
                    'delivery_address',
                    'pharmacy',
                    'items'
                ]
            ]);

        // Vérifier que le stock a été décrémenté
        $this->assertDatabaseHas('stocks', [
            'pharmacy_id' => $this->pharmacy->id,
            'product_id' => $this->product->id,
            'quantity' => 98 // 100 - 2
        ]);
    }

    /**
     * Test order creation with insufficient stock.
     */
    public function test_order_creation_fails_with_insufficient_stock(): void
    {
        $token = $this->user->createToken('auth_token')->plainTextToken;

        $orderData = [
            'pharmacy_id' => $this->pharmacy->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 150 // Plus que le stock disponible
                ]
            ],
            'delivery_address' => '123 Test Street'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/orders', $orderData);

        $response->assertStatus(422)
            ->assertJson(['error' => 'Insufficient stock']);
    }

    /**
     * Test order listing.
     */
    public function test_can_list_user_orders(): void
    {
        $token = $this->user->createToken('auth_token')->plainTextToken;

        // Créer une commande
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'pharmacy_id' => $this->pharmacy->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/orders');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'status',
                        'total_price',
                        'delivery_address',
                        'pharmacy',
                        'items'
                    ]
                ],
                'links',
                'meta'
            ]);
    }

    /**
     * Test order details.
     */
    public function test_can_show_order_details(): void
    {
        $token = $this->user->createToken('auth_token')->plainTextToken;

        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'pharmacy_id' => $this->pharmacy->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson("/api/orders/{$order->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'status',
                'total_price',
                'delivery_address',
                'pharmacy',
                'items',
                'can_cancel',
                'can_review'
            ]);
    }

    /**
     * Test order status update by admin.
     */
    public function test_admin_can_update_order_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'pharmacy_id' => $this->pharmacy->id,
            'status' => 'pending'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->patchJson("/api/orders/{$order->id}/status", [
            'status' => 'confirmed'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Order status updated successfully',
                'order' => [
                    'status' => 'confirmed'
                ]
            ]);
    }

    /**
     * Test order status update by delivery driver.
     */
    public function test_delivery_driver_can_update_order_status(): void
    {
        $driver = User::factory()->create(['role' => 'livreur']);
        $token = $driver->createToken('auth_token')->plainTextToken;

        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'pharmacy_id' => $this->pharmacy->id,
            'status' => 'confirmed'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->patchJson("/api/orders/{$order->id}/status", [
            'status' => 'delivering'
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test unauthorized order status update.
     */
    public function test_unauthorized_user_cannot_update_order_status(): void
    {
        $otherUser = User::factory()->create(['role' => 'client']);
        $token = $otherUser->createToken('auth_token')->plainTextToken;

        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'pharmacy_id' => $this->pharmacy->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->patchJson("/api/orders/{$order->id}/status", [
            'status' => 'confirmed'
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test order cancellation with stock restoration.
     */
    public function test_order_cancellation_restores_stock(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'pharmacy_id' => $this->pharmacy->id,
            'status' => 'confirmed'
        ]);

        $initialStock = Stock::where('pharmacy_id', $this->pharmacy->id)
            ->where('product_id', $this->product->id)
            ->first()->quantity;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->patchJson("/api/orders/{$order->id}/status", [
            'status' => 'cancelled'
        ]);

        $response->assertStatus(200);

        // Vérifier que le stock a été restauré
        $finalStock = Stock::where('pharmacy_id', $this->pharmacy->id)
            ->where('product_id', $this->product->id)
            ->first()->quantity;

        $this->assertEquals($initialStock + 1, $finalStock);
    }

    /**
     * Test order validation.
     */
    public function test_order_validation(): void
    {
        $token = $this->user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/orders', [
            'pharmacy_id' => '',
            'items' => [],
            'delivery_address' => ''
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['pharmacy_id', 'items', 'delivery_address']);
    }

    /**
     * Test accessing another user's order.
     */
    public function test_cannot_access_other_user_order(): void
    {
        $otherUser = User::factory()->create();
        $token = $otherUser->createToken('auth_token')->plainTextToken;

        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'pharmacy_id' => $this->pharmacy->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson("/api/orders/{$order->id}");

        $response->assertStatus(403);
    }
}

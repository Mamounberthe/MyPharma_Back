<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Pharmacy;
use App\Models\Product;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private function createStockedPharmacy(): array
    {
        $pharmacy = Pharmacy::factory()->create();
        $category = Category::factory()->create();
        $product  = Product::factory()->create(['category_id' => $category->id]);
        $stock    = Stock::factory()->create([
            'pharmacy_id' => $pharmacy->id,
            'product_id'  => $product->id,
            'quantity'    => 20,
            'price'       => 2500,
        ]);
        return compact('pharmacy', 'product', 'stock');
    }

    public function test_client_can_create_order(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        ['pharmacy' => $pharmacy, 'product' => $product] = $this->createStockedPharmacy();

        $response = $this->actingAs($user, 'sanctum')
             ->postJson('/api/v1/orders', [
                 'pharmacy_id'      => $pharmacy->id,
                 'items'            => [['product_id' => $product->id, 'quantity' => 2]],
                 'delivery_address' => '123 Rue Test, Dakar',
             ]);

        $response->assertStatus(201)
                 ->assertJsonStructure(['order' => ['id', 'status', 'total_price']]);

        $this->assertDatabaseHas('orders', [
            'user_id'     => $user->id,
            'pharmacy_id' => $pharmacy->id,
            'status'      => 'pending',
        ]);
    }

    public function test_order_fails_when_stock_insufficient(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        ['pharmacy' => $pharmacy, 'product' => $product, 'stock' => $stock] = $this->createStockedPharmacy();
        $stock->update(['quantity' => 1]);

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/v1/orders', [
                 'pharmacy_id'      => $pharmacy->id,
                 'items'            => [['product_id' => $product->id, 'quantity' => 5]],
                 'delivery_address' => '123 Rue Test',
             ])
             ->assertStatus(422);
    }

    public function test_client_can_list_own_orders(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        Order::factory()->count(3)->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
             ->getJson('/api/v1/orders')
             ->assertStatus(200)
             ->assertJsonStructure(['data', 'pagination']);
    }

    public function test_admin_can_update_order_status(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $client = User::factory()->create(['role' => 'client']);
        $order  = Order::factory()->create(['user_id' => $client->id, 'status' => 'pending']);

        $this->actingAs($admin, 'sanctum')
             ->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'confirmed'])
             ->assertStatus(200);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'confirmed']);
    }

    public function test_non_admin_cannot_access_admin_routes(): void
    {
        $user = User::factory()->create(['role' => 'client']);

        $this->actingAs($user, 'sanctum')
             ->getJson('/api/v1/admin/stats')
             ->assertStatus(403);
    }

    public function test_categories_endpoint_is_public(): void
    {
        Category::factory()->count(3)->create();

        $this->getJson('/api/v1/categories')
             ->assertStatus(200)
             ->assertJsonStructure([['id', 'name']]);
    }
}

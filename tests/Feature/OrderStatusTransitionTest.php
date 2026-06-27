<?php

namespace Tests\Feature;

use App\Models\Pharmacy;
use App\Models\Product;
use App\Models\Stock;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        return $admin;
    }

    /**
     * Crée une commande réelle (décrémente le stock) et renvoie [order, stock].
     */
    private function makeOrder(int $initialStock = 50): array
    {
        $pharmacy = Pharmacy::factory()->create();
        $product  = Product::factory()->create(['requires_prescription' => false]);
        $stock    = Stock::factory()->create([
            'pharmacy_id' => $pharmacy->id,
            'product_id'  => $product->id,
            'quantity'    => $initialStock,
            'price'       => 1500,
        ]);

        $order = app(OrderService::class)->createOrder([
            'pharmacy_id'      => $pharmacy->id,
            'items'            => [['product_id' => $product->id, 'quantity' => 2]],
            'delivery_address' => 'Rue 10, Bamako',
        ], User::factory()->create()->id);

        return [$order, $stock];
    }

    public function test_valid_status_flow_is_accepted(): void
    {
        [$order] = $this->makeOrder();
        $admin = $this->admin();

        foreach (['confirmed', 'delivering', 'delivered'] as $status) {
            $this->actingAs($admin, 'sanctum')
                ->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => $status])
                ->assertStatus(200)
                ->assertJsonPath('order.status', $status);
        }
    }

    public function test_illegal_transition_is_rejected(): void
    {
        [$order] = $this->makeOrder();

        // pending -> delivered (saute confirmed/delivering) : interdit.
        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'delivered'])
            ->assertStatus(422);

        $this->assertEquals('pending', $order->fresh()->status);
    }

    public function test_cancelling_twice_does_not_double_restock(): void
    {
        [$order, $stock] = $this->makeOrder(50);
        $admin = $this->admin();

        // Après commande de 2 unités : stock = 48.
        $this->assertEquals(48, $stock->fresh()->quantity);

        // Première annulation : stock restitué à 50.
        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'cancelled'])
            ->assertStatus(200);
        $this->assertEquals(50, $stock->fresh()->quantity);

        // Seconde annulation : refusée, stock INCHANGÉ (pas de 52).
        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'cancelled'])
            ->assertStatus(422);
        $this->assertEquals(50, $stock->fresh()->quantity);
    }

    public function test_delivered_order_is_terminal(): void
    {
        [$order] = $this->makeOrder();
        $admin = $this->admin();

        foreach (['confirmed', 'delivering', 'delivered'] as $status) {
            $this->actingAs($admin, 'sanctum')
                ->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => $status])
                ->assertStatus(200);
        }

        // delivered -> cancelled : interdit (terminal).
        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'cancelled'])
            ->assertStatus(422);
    }
}

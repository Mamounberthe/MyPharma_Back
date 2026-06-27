<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Pharmacy;
use App\Models\Product;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrescriptionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function pharmacyWithProduct(bool $requiresPrescription): array
    {
        $pharmacy = Pharmacy::factory()->create();
        $product  = Product::factory()->create(['requires_prescription' => $requiresPrescription]);
        Stock::factory()->create([
            'pharmacy_id' => $pharmacy->id,
            'product_id'  => $product->id,
            'quantity'    => 50,
            'price'       => 2000,
        ]);

        return [$pharmacy, $product];
    }

    private function placeOrder(User $user, Pharmacy $pharmacy, Product $product): Order
    {
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/orders', [
            'pharmacy_id'      => $pharmacy->id,
            'items'            => [['product_id' => $product->id, 'quantity' => 1]],
            'delivery_address' => 'Rue 123, Bamako',
        ])->assertStatus(201);

        return Order::findOrFail($response->json('id') ?? $response->json('order.id') ?? $response->json('data.id'));
    }

    public function test_order_without_prescription_product_is_not_flagged(): void
    {
        [$pharmacy, $product] = $this->pharmacyWithProduct(false);
        $order = $this->placeOrder(User::factory()->create(), $pharmacy, $product);

        $this->assertEquals(Order::RX_NOT_REQUIRED, $order->prescription_status);
        $this->assertFalse($order->isBlockedByPrescription());
    }

    public function test_order_with_prescription_product_is_pending_review(): void
    {
        [$pharmacy, $product] = $this->pharmacyWithProduct(true);
        $order = $this->placeOrder(User::factory()->create(), $pharmacy, $product);

        $this->assertEquals(Order::RX_PENDING, $order->prescription_status);
        $this->assertTrue($order->isBlockedByPrescription());
    }

    public function test_payment_is_blocked_until_prescription_is_approved(): void
    {
        [$pharmacy, $product] = $this->pharmacyWithProduct(true);
        $user  = User::factory()->create();
        $order = $this->placeOrder($user, $pharmacy, $product);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/pay", ['payment_method' => 'cash'])
            ->assertStatus(422)
            ->assertJsonPath('status', 'prescription_required');

        $this->assertEquals('pending', $order->fresh()->status);
    }

    public function test_full_cycle_upload_approve_then_pay(): void
    {
        Storage::fake('public');
        [$pharmacy, $product] = $this->pharmacyWithProduct(true);
        $user  = User::factory()->create();
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $order = $this->placeOrder($user, $pharmacy, $product);

        // 1. Le client téléverse son ordonnance.
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/prescription", [
                'file' => UploadedFile::fake()->create('ordonnance.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(200)
            ->assertJsonPath('prescription_status', Order::RX_PENDING);

        // 2. La commande apparaît dans la file du pharmacien.
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/prescriptions/pending')
            ->assertStatus(200)
            ->assertJsonPath('pagination.total', 1);

        // 3. Le pharmacien approuve.
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/prescriptions/{$order->id}/approve")
            ->assertStatus(200);

        $order->refresh();
        $this->assertEquals(Order::RX_APPROVED, $order->prescription_status);
        $this->assertEquals($admin->id, $order->prescription_reviewed_by);

        // 4. Le paiement est désormais possible.
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/pay", ['payment_method' => 'cash'])
            ->assertStatus(200)
            ->assertJsonPath('status', 'confirmed');
    }

    public function test_rejection_requires_reason_and_reblocks_payment(): void
    {
        Storage::fake('public');
        [$pharmacy, $product] = $this->pharmacyWithProduct(true);
        $user  = User::factory()->create();
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $order = $this->placeOrder($user, $pharmacy, $product);

        $this->actingAs($user, 'sanctum')->postJson("/api/v1/orders/{$order->id}/prescription", [
            'file' => UploadedFile::fake()->create('ordonnance.pdf', 100, 'application/pdf'),
        ])->assertStatus(200);

        // Motif obligatoire.
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/prescriptions/{$order->id}/reject", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);

        // Refus avec motif.
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/prescriptions/{$order->id}/reject", [
                'reason' => 'Ordonnance illisible, merci de renvoyer une photo nette.',
            ])
            ->assertStatus(200);

        $this->assertEquals(Order::RX_REJECTED, $order->fresh()->prescription_status);

        // Le paiement reste bloqué après un refus.
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/pay", ['payment_method' => 'cash'])
            ->assertStatus(422)
            ->assertJsonPath('prescription_status', Order::RX_REJECTED);

        // Un nouvel envoi relance le cycle de validation.
        $this->actingAs($user, 'sanctum')->postJson("/api/v1/orders/{$order->id}/prescription", [
            'file' => UploadedFile::fake()->create('ordonnance2.pdf', 100, 'application/pdf'),
        ])->assertStatus(200);

        $order->refresh();
        $this->assertEquals(Order::RX_PENDING, $order->prescription_status);
        $this->assertNull($order->prescription_rejection_reason);
        $this->assertNull($order->prescription_reviewed_by);
    }

    public function test_non_admin_cannot_review_prescriptions(): void
    {
        [$pharmacy, $product] = $this->pharmacyWithProduct(true);
        $user  = User::factory()->create();
        $order = $this->placeOrder($user, $pharmacy, $product);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/prescriptions/pending')
            ->assertStatus(403);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/admin/prescriptions/{$order->id}/approve")
            ->assertStatus(403);
    }

    public function test_cannot_approve_order_that_does_not_require_prescription(): void
    {
        [$pharmacy, $product] = $this->pharmacyWithProduct(false);
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $order = $this->placeOrder(User::factory()->create(), $pharmacy, $product);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/prescriptions/{$order->id}/approve")
            ->assertStatus(422);
    }
}

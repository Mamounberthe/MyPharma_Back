<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private function pendingOrderFor(User $user): Order
    {
        return Order::factory()->create([
            'user_id'     => $user->id,
            'pharmacy_id' => Pharmacy::factory()->create()->id,
            'status'      => 'pending',
        ]);
    }

    public function test_cash_on_delivery_confirms_order(): void
    {
        Mail::fake();
        $user  = User::factory()->create();
        $order = $this->pendingOrderFor($user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/pay", ['payment_method' => 'cash'])
            ->assertStatus(200)
            ->assertJsonPath('status', 'confirmed');

        $this->assertEquals('confirmed', $order->fresh()->status);
        // Le paiement n'est PAS encaissé : il reste en attente jusqu'à la livraison.
        $this->assertDatabaseHas('payments', [
            'order_id'       => $order->id,
            'payment_method' => 'cash',
            'status'         => 'pending',
        ]);
    }

    public function test_online_payment_is_rejected_when_disabled(): void
    {
        config(['payments.online_enabled' => false]);
        $user  = User::factory()->create();
        $order = $this->pendingOrderFor($user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/pay", ['payment_method' => 'orange_money'])
            ->assertStatus(422)
            ->assertJsonPath('status', 'unavailable');

        // La commande ne doit surtout PAS être confirmée.
        $this->assertEquals('pending', $order->fresh()->status);
    }

    public function test_online_payment_when_enabled_stays_pending(): void
    {
        config(['payments.online_enabled' => true]);
        $user  = User::factory()->create();
        $order = $this->pendingOrderFor($user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/pay", ['payment_method' => 'wave'])
            ->assertStatus(202)
            ->assertJsonPath('status', 'pending');

        // Sans webhook prestataire confirmé, la commande reste "pending".
        $this->assertEquals('pending', $order->fresh()->status);
    }

    public function test_cannot_pay_someone_elses_order(): void
    {
        $owner    = User::factory()->create();
        $attacker = User::factory()->create();
        $order    = $this->pendingOrderFor($owner);

        $this->actingAs($attacker, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/pay", ['payment_method' => 'cash'])
            ->assertStatus(403);
    }
}

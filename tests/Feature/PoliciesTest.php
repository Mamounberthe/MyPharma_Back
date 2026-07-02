<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Pharmacy;
use App\Models\Product;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PoliciesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        return $admin;
    }

    private function client(): User
    {
        return User::factory()->create(['role' => 'client']);
    }

    public function test_product_policy_allows_admin_to_create(): void
    {
        $admin = $this->admin();

        $this->assertTrue($admin->can('create', Product::class));
    }

    public function test_product_policy_denies_client_to_create(): void
    {
        $client = $this->client();

        $this->assertFalse($client->can('create', Product::class));
    }

    public function test_pharmacy_policy_allows_admin_to_delete(): void
    {
        $admin = $this->admin();
        $pharmacy = Pharmacy::factory()->create();

        $this->assertTrue($admin->can('delete', $pharmacy));
    }

    public function test_category_policy_allows_admin_to_update(): void
    {
        $admin = $this->admin();
        $category = Category::factory()->create();

        $this->assertTrue($admin->can('update', $category));
    }

    public function test_stock_policy_allows_admin_to_view(): void
    {
        $admin = $this->admin();

        $this->assertTrue($admin->can('viewAny', Stock::class));
    }

    public function test_stock_policy_denies_client_to_view(): void
    {
        $client = $this->client();

        $this->assertFalse($client->can('viewAny', Stock::class));
    }

    public function test_notification_policy_allows_owner_to_view(): void
    {
        $user = User::factory()->create();
        $notification = \App\Models\Notification::create([
            'user_id' => $user->id,
            'type' => 'test',
            'title' => 'Test Notification',
            'body' => 'Test body',
            'data' => [],
        ]);

        $this->assertTrue($user->can('view', $notification));
    }

    public function test_notification_policy_denies_non_owner_to_view(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $notification = \App\Models\Notification::create([
            'user_id' => $otherUser->id,
            'type' => 'test',
            'title' => 'Test Notification',
            'body' => 'Test body',
            'data' => [],
        ]);

        $this->assertFalse($user->can('view', $notification));
    }

    public function test_payment_policy_allows_order_owner_to_view(): void
    {
        $user = User::factory()->create();
        $order = \App\Models\Order::factory()->create(['user_id' => $user->id]);
        $payment = \App\Models\Payment::create([
            'order_id' => $order->id,
            'amount' => 100,
            'status' => 'completed',
            'payment_method' => 'cash',
        ]);

        $this->assertTrue($user->can('view', $payment));
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        return $admin;
    }

    public function test_unauthenticated_user_is_rejected_from_admin(): void
    {
        $this->getJson('/api/v1/admin/stats')->assertStatus(401);
    }

    public function test_client_is_forbidden_from_admin(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/admin/stats')
            ->assertStatus(403);
    }

    public function test_admin_can_access_admin(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/admin/stats')
            ->assertStatus(200);
    }
}

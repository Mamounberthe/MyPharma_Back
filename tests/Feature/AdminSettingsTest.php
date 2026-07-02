<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        return $admin;
    }

    public function test_admin_can_view_settings(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/admin/settings')
            ->assertStatus(200)
            ->assertJsonStructure(['app', 'delivery']);
    }

    public function test_admin_can_update_settings(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson('/api/v1/admin/settings', [
                'delivery_enabled' => false,
                'delivery_base_fee' => 10.00,
            ])
            ->assertStatus(200);
    }

    public function test_client_cannot_access_settings(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/admin/settings')
            ->assertStatus(403);
    }
}

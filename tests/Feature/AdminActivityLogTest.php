<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        return $admin;
    }

    public function test_admin_can_view_activity_log(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/admin/activity-log')
            ->assertStatus(200)
            ->assertJsonStructure(['orders', 'users_created', 'pharmacies_created']);
    }

    public function test_admin_can_filter_activity_log_by_date(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/admin/activity-log?start_date=2024-01-01&end_date=2024-12-31')
            ->assertStatus(200);
    }

    public function test_client_cannot_access_activity_log(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/admin/activity-log')
            ->assertStatus(403);
    }
}

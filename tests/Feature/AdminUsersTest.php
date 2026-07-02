<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        return $admin;
    }

    public function test_admin_can_list_users(): void
    {
        User::factory()->count(5)->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/admin/users')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'pagination']);
    }

    public function test_admin_can_create_user(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/users', [
                'name' => 'New User',
                'email' => 'new@example.com',
                'password' => 'password123',
                'role' => 'livreur',
            ])
            ->assertStatus(201)
            ->assertJsonPath('user.role', 'livreur');
    }

    public function test_admin_can_update_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson('/api/v1/admin/users/' . $user->id, [
                'name' => 'Updated Name',
                'role' => 'admin',
            ])
            ->assertStatus(200)
            ->assertJsonPath('user.name', 'Updated Name');
    }

    public function test_admin_can_delete_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson('/api/v1/admin/users/' . $user->id)
            ->assertStatus(200);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/v1/admin/users/' . $admin->id)
            ->assertStatus(422);
    }

    public function test_client_cannot_access_admin_users(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/admin/users')
            ->assertStatus(403);
    }
}

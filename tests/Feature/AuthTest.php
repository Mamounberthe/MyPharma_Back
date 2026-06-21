<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name'                  => 'Test User',
            'email'                 => 'test@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure(['user' => ['id', 'name', 'email', 'role'], 'token']);

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);

        $response = $this->postJson('/api/v1/login', [
            'email'    => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['user', 'token']);
    }

    public function test_login_with_wrong_password_returns_401(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct')]);

        $this->postJson('/api/v1/login', [
            'email'    => $user->email,
            'password' => 'wrong',
        ])->assertStatus(401);
    }

    public function test_authenticated_user_can_get_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
             ->getJson('/api/v1/user')
             ->assertStatus(200)
             ->assertJsonPath('email', $user->email);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/user')->assertStatus(401);
    }

    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
             ->patchJson('/api/v1/user', ['name' => 'New Name', 'email' => $user->email])
             ->assertStatus(200)
             ->assertJsonPath('user.name', 'New Name');
    }

    public function test_user_can_change_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('oldpass123')]);

        $this->actingAs($user, 'sanctum')
             ->patchJson('/api/v1/user/password', [
                 'current_password'      => 'oldpass123',
                 'password'              => 'newpass456',
                 'password_confirmation' => 'newpass456',
             ])
             ->assertStatus(200);
    }

    public function test_change_password_fails_with_wrong_current(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct123')]);

        $this->actingAs($user, 'sanctum')
             ->patchJson('/api/v1/user/password', [
                 'current_password'      => 'wrong',
                 'password'              => 'newpass456',
                 'password_confirmation' => 'newpass456',
             ])
             ->assertStatus(422);
    }
}

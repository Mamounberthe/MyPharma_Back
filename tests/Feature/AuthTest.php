<?php

namespace Tests\Feature;

use App\Mail\PasswordResetNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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

    public function test_user_can_logout(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
             ->postJson('/api/v1/logout')
             ->assertStatus(200);

        // Le token doit être révoqué après logout.
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_registration_validation_rejects_invalid_payload(): void
    {
        $this->postJson('/api/v1/register', [
            'name'     => '',
            'email'    => 'not-an-email',
            'password' => '123',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    /**
     * Anti-énumération de comptes : la réponse de "mot de passe oublié" doit être
     * identique que l'email existe ou non.
     */
    public function test_forgot_password_does_not_reveal_account_existence(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'known@example.com']);

        $existing = $this->postJson('/api/v1/forgot-password', ['email' => 'known@example.com'])
            ->assertStatus(200);

        $unknown = $this->postJson('/api/v1/forgot-password', ['email' => 'nobody@example.com'])
            ->assertStatus(200);

        // Réponse identique → impossible de distinguer un compte existant.
        $this->assertSame($existing->json('message'), $unknown->json('message'));

        // Mais l'email de réinitialisation n'est réellement envoyé qu'au compte existant.
        Mail::assertSent(PasswordResetNotification::class, 1);
        Mail::assertSent(
            PasswordResetNotification::class,
            fn (PasswordResetNotification $mail) => $mail->hasTo('known@example.com')
        );
    }

    /**
     * Non-régression sécurité : l'inscription publique ne doit JAMAIS permettre
     * de choisir son rôle. Tout compte créé via /register est un "client",
     * même si la requête tente d'injecter "role: admin".
     */
    public function test_registration_cannot_escalate_to_admin(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name'                  => 'Pentester',
            'email'                 => 'pentest@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role'                  => 'admin',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('user.role', 'client');

        $this->assertDatabaseHas('users', [
            'email' => 'pentest@example.com',
            'role'  => 'client',
        ]);
    }
}

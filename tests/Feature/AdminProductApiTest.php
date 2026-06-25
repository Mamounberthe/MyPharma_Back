<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminProductApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Category::factory()->create(['name' => 'Médicaments']);
    }

    public function test_non_admin_cannot_access_admin_products()
    {
        $user = User::factory()->create(['role' => 'client']);
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/v1/admin/products');

        $response->assertStatus(403);
    }

    public function test_admin_can_list_admin_products()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $category = Category::first();
        Product::factory()->count(3)->create(['category_id' => $category->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/v1/admin/products');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_admin_can_create_product()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $category = Category::first();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/v1/admin/products', [
            'name'                  => 'Nouveau Produit',
            'sku'                   => 'SKU12345',
            'price'                 => 15.99,
            'description'           => 'Une description courte.',
            'category_id'           => $category->id,
            'requires_prescription' => true
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('name', 'Nouveau Produit')
            ->assertJsonPath('sku', 'SKU12345')
            ->assertJsonPath('price', '15.99')
            ->assertJsonPath('requires_prescription', true);

        $this->assertDatabaseHas('products', [
            'name' => 'Nouveau Produit',
            'sku'  => 'SKU12345'
        ]);
    }

    public function test_admin_can_update_product()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $category = Category::first();
        $product = Product::factory()->create(['category_id' => $category->id, 'name' => 'Old Name']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->putJson("/api/v1/admin/products/{$product->id}", [
            'name' => 'New Name',
            'price' => 20.00
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('name', 'New Name')
            ->assertJsonPath('price', '20.00');

        $this->assertDatabaseHas('products', [
            'id'   => $product->id,
            'name' => 'New Name'
        ]);
    }

    public function test_admin_can_delete_product()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $category = Category::first();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->deleteJson("/api/v1/admin/products/{$product->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_admin_can_upload_product_image()
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $category = Category::first();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $imageFile = UploadedFile::fake()->image('med.jpg');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson("/api/v1/admin/products/{$product->id}/images", [
            'image' => $imageFile
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['image_url', 'message']);

        $imageUrl = $response->json('image_url');
        $this->assertNotNull($imageUrl);
        $this->assertStringContainsString('storage/products/', $imageUrl);

        $filename = basename($imageUrl);
        Storage::disk('public')->assertExists('products/' . $filename);
    }
}

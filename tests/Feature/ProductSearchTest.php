<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use App\Models\Product;
use App\Models\Pharmacy;
use App\Models\Stock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer des données de test
        $category = Category::factory()->create(['name' => 'Médicaments']);
        $pharmacy = Pharmacy::factory()->create([
            'latitude' => 48.8566,
            'longitude' => 2.3522
        ]);

        $product = Product::factory()->create([
            'name' => 'Paracétamol 500mg',
            'category_id' => $category->id
        ]);

        Stock::factory()->create([
            'pharmacy_id' => $pharmacy->id,
            'product_id' => $product->id,
            'quantity' => 50,
            'price' => 8.99
        ]);
    }

    /**
     * La recherche est volontairement PUBLIQUE (boutique B2C : on parcourt le
     * catalogue avant de s'inscrire). Voir le groupe "Routes publiques" dans
     * routes/api.php.
     */
    public function test_product_search_is_public(): void
    {
        $response = $this->getJson('/api/v1/search?query=paracetamol');

        $response->assertStatus(200);
    }

    /**
     * Test basic product search.
     */
    public function test_can_search_products(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/v1/search?query=paracetamol');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'query',
                'results' => [
                    '*' => [
                        'product',
                        'available_pharmacies',
                        'total_pharmacies',
                        'min_price',
                        'max_price'
                    ]
                ],
                'total_results'
            ]);
    }

    /**
     * Test product search with location filter.
     */
    public function test_search_with_location_filter(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/v1/search?query=paracetamol&latitude=48.8566&longitude=2.3522&radius=5');

        $response->assertStatus(200);

        // Vérifier que la distance est calculée
        $results = $response->json('results');
        if (!empty($results)) {
            $this->assertArrayHasKey('distance', $results[0]['available_pharmacies'][0]['pharmacy']);
        }
    }

    /**
     * Test product search with price filter.
     */
    public function test_search_with_price_filter(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/v1/search?query=paracetamol&min_price=5&max_price=10');

        $response->assertStatus(200);

        $results = $response->json('results');
        if (!empty($results)) {
            $this->assertGreaterThanOrEqual(5, $results[0]['min_price']);
            $this->assertLessThanOrEqual(10, $results[0]['max_price']);
        }
    }

    /**
     * Test product search with rating filter.
     */
    public function test_search_with_rating_filter(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/v1/search?query=paracetamol&min_rating=4');

        $response->assertStatus(200);
    }

    /**
     * Test product search with no results.
     */
    public function test_search_returns_no_results_for_nonexistent_product(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/v1/search?query=nonexistentproduct');

        $response->assertStatus(200)
            ->assertJson([
                'query' => 'nonexistentproduct',
                'total_results' => 0,
                'results' => []
            ]);
    }

    /**
     * Test product search validation.
     */
    public function test_search_validation(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/v1/search');

        // Validation Laravel standard : 422 avec une erreur sur le champ "query".
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['query']);
    }

    /**
     * Test products listing.
     */
    public function test_can_list_products(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/v1/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'category',
                        'created_at',
                        'updated_at'
                    ]
                ],
                'pagination' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    /**
     * Test product details.
     */
    public function test_can_show_product_details(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;
        $product = Product::first();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'description',
                'category',
                'stocks',
                'created_at',
                'updated_at'
            ]);
    }

    /**
     * La recherche doit ignorer la casse (majuscule/minuscule). Portable
     * SQLite/PostgreSQL.
     */
    public function test_search_is_case_insensitive(): void
    {
        Product::factory()->create([
            'name'        => 'Doliprane',
            'category_id' => Category::first()->id,
        ]);

        foreach (['doliprane', 'DOLIPRANE', 'DoLiPrAnE', 'doli'] as $term) {
            $names = collect(
                $this->getJson('/api/v1/products?search=' . $term)
                    ->assertStatus(200)
                    ->json('data')
            )->pluck('name');

            $this->assertContains(
                'Doliprane',
                $names,
                "La recherche « {$term} » devrait trouver Doliprane."
            );
        }
    }

    /**
     * Rechercher avec l'accent trouve le produit accentué. L'insensibilité
     * TOTALE aux accents (« paracetamol » → « Paracétamol ») repose sur
     * l'extension PostgreSQL unaccent, active en production.
     */
    public function test_search_matches_accented_name(): void
    {
        $names = collect(
            $this->getJson('/api/v1/products?search=' . urlencode('paracétamol'))
                ->assertStatus(200)
                ->json('data')
        )->pluck('name');

        $this->assertContains('Paracétamol 500mg', $names);
    }
}

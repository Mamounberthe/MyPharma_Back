<?php

namespace Tests\Feature;

use App\Models\Pharmacy;
use App\Models\User;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PharmacyApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create(['role' => 'client']);
        Sanctum::actingAs($this->user);
    }

    public function test_can_list_pharmacies()
    {
        Pharmacy::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/pharmacies');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'address',
                        'location',
                        'phone',
                        'rating',
                        'delivery_available',
                        'created_at',
                        'updated_at'
                    ]
                ],
                'pagination'
            ]);
    }

    public function test_can_show_pharmacy()
    {
        $pharmacy = Pharmacy::factory()->create();

        $response = $this->getJson("/api/v1/pharmacies/{$pharmacy->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'address',
                'location',
                'phone',
                'rating',
                'delivery_available',
                'created_at',
                'updated_at'
            ]);
    }

    public function test_show_pharmacy_returns_404_when_not_found()
    {
        $response = $this->getJson('/api/v1/pharmacies/999999');

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'Pharmacy not found'
            ]);
    }

    public function test_can_list_pharmacy_reviews()
    {
        $pharmacy = Pharmacy::factory()->create();
        Review::factory()->count(5)->create(['pharmacy_id' => $pharmacy->id]);

        $response = $this->getJson("/api/v1/pharmacies/{$pharmacy->id}/reviews");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'rating',
                        'comment',
                        'created_at',
                        'updated_at',
                        'user' => [
                            'id',
                            'name'
                        ]
                    ]
                ],
                'pagination'
            ]);
    }

    public function test_can_filter_pharmacies_by_location()
    {
        Pharmacy::factory()->create([
            'latitude' => 48.8566,
            'longitude' => 2.3522,
            'rating' => 4.5
        ]);

        $response = $this->getJson('/api/v1/pharmacies?latitude=48.8566&longitude=2.3522&radius=10');

        $response->assertStatus(200);
    }

    public function test_can_filter_pharmacies_by_rating()
    {
        Pharmacy::factory()->create(['rating' => 4.5]);
        Pharmacy::factory()->create(['rating' => 3.0]);

        $response = $this->getJson('/api/v1/pharmacies?min_rating=4');

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data')));
    }
}

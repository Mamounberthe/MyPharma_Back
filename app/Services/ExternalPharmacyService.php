<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Pharmacy;

class ExternalPharmacyService
{
    protected ?string $googleApiKey;
    protected string $osmBaseUrl = 'https://nominatim.openstreetmap.org';

    public function __construct()
    {
        $this->googleApiKey = config('services.google_maps.key') ?? '';
    }

    /**
     * Rechercher des pharmacies via Google Places API
     */
    public function searchGooglePlaces(float $lat, float $lng, int $radius = 5000): array
    {
        if (empty($this->googleApiKey)) {
            Log::warning('Google Maps API key is missing.');
            return [];
        }

        try {
            $response = Http::get('https://maps.googleapis.com/maps/api/place/nearbysearch/json', [
                'location' => "{$lat},{$lng}",
                'radius' => $radius,
                'type' => 'pharmacy',
                'key' => $this->googleApiKey
            ]);

            if ($response->failed()) {
                Log::error('Google Places API error: ' . $response->body());
                return [];
            }

            $results = $response->json()['results'] ?? [];
            
            return array_map(function ($place) {
                return [
                    'name' => $place['name'],
                    'address' => $place['vicinity'] ?? $place['formatted_address'] ?? 'Adresse non spécifiée',
                    'latitude' => $place['geometry']['location']['lat'],
                    'longitude' => $place['geometry']['location']['lng'],
                    'google_place_id' => $place['place_id'],
                    'rating' => $place['rating'] ?? 0,
                    'is_partner' => false,
                    'status' => 'active'
                ];
            }, $results);

        } catch (\Exception $e) {
            Log::error('Exception during Google Places search: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Rechercher des pharmacies via OpenStreetMap (Overpass API)
     */
    public function searchOSM(float $lat, float $lng, int $radius = 5000): array
    {
        try {
            // Utilisation de Overpass API pour trouver les pharmacies
            $overpassUrl = 'https://overpass-api.de/api/interpreter';
            $query = "[out:json];node(around:{$radius},{$lat},{$lng})[amenity=pharmacy];out;";
            
            $response = Http::get($overpassUrl, ['data' => $query]);

            if ($response->failed()) {
                Log::error('OSM Overpass API error: ' . $response->body());
                return [];
            }

            $elements = $response->json()['elements'] ?? [];

            return array_map(function ($element) {
                $tags = $element['tags'] ?? [];
                return [
                    'name' => $tags['name'] ?? 'Pharmacie (OSM)',
                    'address' => $tags['addr:full'] ?? ($tags['addr:street'] ?? 'Adresse inconnue'),
                    'latitude' => $element['lat'],
                    'longitude' => $element['lon'],
                    'osm_id' => $element['id'],
                    'phone' => $tags['phone'] ?? $tags['contact:phone'] ?? null,
                    'is_partner' => false,
                    'status' => 'active'
                ];
            }, $elements);

        } catch (\Exception $e) {
            Log::error('Exception during OSM search: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Fusionner les résultats locaux et externes
     */
    public function getMergedPharmacies(float $lat, float $lng, int $radius = 5000): array
    {
        // 1. Pharmacies locales (Partenaires)
        $localPharmacies = Pharmacy::where('is_partner', true)
            ->selectRaw(
                "*, (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance",
                [$lat, $lng, $lat]
            )
            ->having('distance', '<=', $radius / 1000)
            ->orderBy('distance')
            ->get()
            ->toArray();

        // 2. Pharmacies externes (Google/OSM)
        // Note: On pourrait mettre en cache ces résultats
        $externalOSM = $this->searchOSM($lat, $lng, $radius);
        
        // 3. Fusion et dédoublonnage (basé sur la proximité géographique)
        $merged = $localPharmacies;
        
        foreach ($externalOSM as $ext) {
            $isDuplicate = false;
            foreach ($localPharmacies as $loc) {
                $dist = $this->calculateDistance($ext['latitude'], $ext['longitude'], $loc['latitude'], $loc['longitude']);
                if ($dist < 0.05) { // Moins de 50 mètres = probablement la même
                    $isDuplicate = true;
                    break;
                }
            }
            
            if (!$isDuplicate) {
                $merged[] = $ext;
            }
        }

        return $merged;
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;
        return $miles * 1.609344; // Kilomètres
    }
}

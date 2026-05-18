<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pharmacy;
use Illuminate\Http\Request;

class PharmacyController extends Controller
{
    public function index(Request $request)
    {
        $query = Pharmacy::with(['reviews' => function($query) {
            $query->latest()->limit(5);
        }]);

        // Filtre par distance si lat/lng fournis
        if ($request->has('latitude') && $request->has('longitude')) {
            $lat = $request->latitude;
            $lng = $request->longitude;
            $radius = $request->radius ?? 10; // 10km par défaut

            $query->selectRaw(
                "*, (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance",
                [$lat, $lng, $lat]
            )->having('distance', '<=', $radius)->orderBy('distance');
        }

        // Filtre par note minimale
        if ($request->has('min_rating')) {
            $query->where('rating', '>=', $request->min_rating);
        }

        // Filtre par disponibilité livraison
        if ($request->has('delivery_available')) {
            $query->where('delivery_available', $request->boolean('delivery_available'));
        }

        // Tri
        $sortBy = $request->sort_by ?? 'rating';
        $sortOrder = $request->sort_order ?? 'desc';

        if (in_array($sortBy, ['rating', 'name', 'created_at'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $pharmacies = $query->paginate($request->per_page ?? 15);

        return response()->json($pharmacies);
    }

    public function show($id)
    {
        $pharmacy = Pharmacy::with(['reviews.user', 'stocks.product.category'])
            ->findOrFail($id);

        return response()->json($pharmacy);
    }

    public function reviews($id, Request $request)
    {
        $pharmacy = Pharmacy::findOrFail($id);

        $reviews = $pharmacy->reviews()
            ->with('user:id,name')
            ->latest()
            ->paginate($request->per_page ?? 10);

        return response()->json($reviews);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminProductController extends Controller
{
    /**
     * S'assurer que seul un administrateur peut accéder à ces routes.
     */
    private function ensureAdmin(): void
    {
        $user = request()->user();
        if (!$user || !$user->isAdmin()) {
            abort(403, 'Accès réservé aux administrateurs.');
        }
    }

    /**
     * Lister les produits (admin)
     */
    public function index(Request $request): JsonResponse
    {
        $this->ensureAdmin();

        $perPage = min((int) $request->get('per_page', 15), 100);
        $products = Product::with('category')->paginate($perPage);

        return response()->json($products);
    }

    /**
     * Créer un nouveau produit
     */
    public function store(Request $request): JsonResponse
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'name'                  => 'required|string|max:255',
            'sku'                   => 'nullable|string|max:100|unique:products,sku',
            // Le prix réel est géré par pharmacie (table stocks). Optionnel ici.
            'price'                 => 'nullable|numeric|min:0',
            'description'           => 'nullable|string|max:2000',
            'category_id'           => 'required|exists:categories,id',
            'requires_prescription' => 'boolean',
        ]);

        $product = Product::create($validated);

        return response()->json($product->load('category'), 201);
    }

    /**
     * Mettre à jour un produit existant
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $this->ensureAdmin();

        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name'                  => 'sometimes|required|string|max:255',
            'sku'                   => 'nullable|string|max:100|unique:products,sku,' . $product->id,
            // Le prix réel est géré par pharmacie (table stocks). Optionnel ici.
            'price'                 => 'sometimes|nullable|numeric|min:0',
            'description'           => 'nullable|string|max:2000',
            'category_id'           => 'sometimes|required|exists:categories,id',
            'requires_prescription' => 'sometimes|boolean',
        ]);

        $product->update($validated);

        return response()->json($product->fresh('category'));
    }

    /**
     * Supprimer un produit
     */
    public function destroy(int $id): JsonResponse
    {
        $this->ensureAdmin();

        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json([
            'message' => 'Produit supprimé avec succès.'
        ]);
    }

    /**
     * Uploader l'image d'un produit
     */
    public function uploadImage(int $id, Request $request): JsonResponse
    {
        $this->ensureAdmin();

        $product = Product::findOrFail($id);

        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $disk = config('filesystems.uploads_disk');

        // Supprimer l'ancienne image si elle existe et n'est pas une URL externe
        if ($product->image_url && !filter_var($product->image_url, FILTER_VALIDATE_URL)) {
            Storage::disk($disk)->delete($product->image_url);
        }

        $path = $request->file('image')->store('products', $disk);
        $absoluteUrl = Storage::disk($disk)->url($path);

        $product->update([
            'image_url' => $absoluteUrl
        ]);

        return response()->json([
            'message'   => 'Image uploadée avec succès.',
            'image_url' => $product->image_url,
            'product'   => $product->fresh('category')
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::withCount('products')->orderBy('name')->get();
        return response()->json($categories);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate(['name' => 'required|string|max:100|unique:categories,name']);
        $category = Category::create($data);

        return response()->json($category, 201);
    }

    public function update(Category $category, Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'name' => 'required|string|max:100|unique:categories,name,' . $category->id,
        ]);
        $category->update($data);

        return response()->json($category);
    }

    public function destroy(Category $category, Request $request): JsonResponse
    {
        $this->ensureAdmin($request);
        $category->delete();

        return response()->json(['message' => 'Catégorie supprimée.']);
    }

    private function ensureAdmin(Request $request): void
    {
        if (! $request->user()?->isAdmin()) {
            abort(403, 'Accès réservé aux administrateurs.');
        }
    }
}

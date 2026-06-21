<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\PharmacyControllerRefactored;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\OrderControllerRefactored;
use App\Http\Controllers\Api\OrderTrackingController;
use App\Http\Controllers\Api\PharmacyInvitationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\ReviewController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| API Routes — MyPharma v1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ---------------------------------------------------------------
    // Auth — limité à 10 tentatives/minute pour éviter le brute force
    // ---------------------------------------------------------------
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login',    [AuthController::class, 'login']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password',  [AuthController::class, 'resetPassword']);
        Route::get('/invitations/validate/{token}', [PharmacyInvitationController::class, 'validateToken']);
    });

    // ---------------------------------------------------------------
    // Routes publiques — Pharmacies et Produits
    // ---------------------------------------------------------------
    Route::middleware('throttle:60,1')->group(function () {
        // Catégories (publiques)
        Route::get('/categories', [CategoryController::class, 'index']);

        // Pharmacies (publiques)
        Route::get('/pharmacies',              [PharmacyControllerRefactored::class, 'index']);
        Route::get('/pharmacies/{id}',         [PharmacyControllerRefactored::class, 'show']);
        Route::get('/pharmacies/{id}/reviews', [PharmacyControllerRefactored::class, 'reviews']);

        // Produits (publiques en lecture seule)
        Route::get('/products',                   [ProductController::class, 'index']);
        Route::get('/products/{id}',              [ProductController::class, 'show']);
        Route::get('/search',                     [ProductController::class, 'search']);
        Route::get('/pharmacies/{id}/products',   [ProductController::class, 'pharmacyProducts']);
        Route::get('/products/stats',             [ProductController::class, 'stats']);
        Route::get('/products/popular',           [ProductController::class, 'popular']);
    });

    // ---------------------------------------------------------------
    // Routes protégées — 60 requêtes/minute par utilisateur
    // ---------------------------------------------------------------
    Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {

        // User
        Route::get('/user',              [AuthController::class, 'user']);
        Route::patch('/user',            [AuthController::class, 'update']);
        Route::patch('/user/password',   [AuthController::class, 'updatePassword']);
        Route::post('/push-token',       [AuthController::class, 'updatePushToken']);
        Route::post('/logout',           [AuthController::class, 'logout']);

        // Produits (écriture protégée)
        Route::post('/products',                   [ProductController::class, 'store']);
        Route::put('/products/{id}',               [ProductController::class, 'update']);
        Route::delete('/products/{id}',            [ProductController::class, 'destroy']);

        // Commandes
        Route::get('/orders',                  [OrderControllerRefactored::class, 'index']);
        Route::post('/orders',                 [OrderControllerRefactored::class, 'store']);
        Route::get('/orders/{id}',             [OrderControllerRefactored::class, 'show']);
        Route::patch('/orders/{id}/status',    [OrderControllerRefactored::class, 'updateStatus']);
        
        // Tracking Livraison
        Route::get('/orders/{order}/tracking',          [OrderControllerRefactored::class, 'tracking']);
        Route::get('/orders/{order}/tracking/location', [OrderTrackingController::class, 'getLocation']);
        Route::post('/orders/{order}/tracking',         [OrderTrackingController::class, 'updateLocation']);
        Route::post('/orders/{order}/pay', [PaymentController::class, 'pay']);
        Route::post('/orders/{order}/prescription', function (\Illuminate\Http\Request $request, \App\Models\Order $order) {
            if ($request->user()->id !== $order->user_id) {
                abort(403);
            }
            $request->validate(['file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120']);
            $path = $request->file('file')->store("prescriptions/{$order->id}", 'public');
            $order->update(['prescription_url' => Storage::disk('public')->url($path)]);
            return response()->json(['prescription_url' => $order->prescription_url]);
        });

        // Invitations
        Route::get('/invitations', [PharmacyInvitationController::class, 'index']);
        Route::post('/invitations', [PharmacyInvitationController::class, 'store']);
        
        // Avis
        Route::post('/reviews',        [ReviewController::class, 'store']);
        Route::put('/reviews/{id}',    [ReviewController::class, 'update']);
        Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);

        // Administration (admin uniquement — vérification dans le contrôleur)
        Route::prefix('admin')->group(function () {
            Route::get('/stats',                         [AdminController::class, 'stats']);
            Route::get('/orders',                        [AdminController::class, 'orders']);
            Route::patch('/orders/{order}/status',       [AdminController::class, 'updateOrderStatus']);
            // Pharmacies
            Route::get('/pharmacies',                    [AdminController::class, 'pharmacies']);
            Route::post('/pharmacies',                   [AdminController::class, 'createPharmacy']);
            Route::put('/pharmacies/{pharmacy}',         [AdminController::class, 'updatePharmacy']);
            Route::delete('/pharmacies/{pharmacy}',      [AdminController::class, 'deletePharmacy']);
            // Stocks
            Route::get('/stocks',                        [AdminController::class, 'stocks']);
            Route::post('/stocks',                       [AdminController::class, 'createStock']);
            Route::patch('/stocks/{stock}',              [AdminController::class, 'updateStock']);
            Route::delete('/stocks/{stock}',             [AdminController::class, 'deleteStock']);
            // Catégories
            Route::post('/categories',                   [CategoryController::class, 'store']);
            Route::put('/categories/{category}',         [CategoryController::class, 'update']);
            Route::delete('/categories/{category}',      [CategoryController::class, 'destroy']);
        });
    });
});

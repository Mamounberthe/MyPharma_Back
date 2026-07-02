<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\PharmacyController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderTrackingController;
use App\Http\Controllers\Api\PharmacyInvitationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PrescriptionController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AdminProductController;
use App\Http\Controllers\Api\AdminPromotionController;
use App\Http\Controllers\Api\ReviewController;
use Illuminate\Support\Facades\Route;

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
        Route::get('/pharmacies',              [PharmacyController::class, 'index']);
        Route::get('/pharmacies/{id}',         [PharmacyController::class, 'show']);
        Route::get('/pharmacies/{id}/reviews', [PharmacyController::class, 'reviews']);

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
        Route::get('/orders',                  [OrderController::class, 'index']);
        Route::post('/orders',                 [OrderController::class, 'store']);
        Route::get('/orders/{order}',          [OrderController::class, 'show']);
        Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);

        // Tracking Livraison
        Route::get('/orders/{order}/tracking',          [OrderController::class, 'tracking']);
        Route::get('/orders/{order}/tracking/location', [OrderTrackingController::class, 'getLocation']);
        Route::post('/orders/{order}/tracking',         [OrderTrackingController::class, 'updateLocation']);
        Route::post('/orders/{order}/pay', [PaymentController::class, 'pay']);
        Route::post('/orders/{order}/prescription', [PrescriptionController::class, 'upload']);

        // Invitations
        Route::get('/invitations', [PharmacyInvitationController::class, 'index']);
        Route::post('/invitations', [PharmacyInvitationController::class, 'store']);
        
        // Avis
        Route::post('/reviews',        [ReviewController::class, 'store']);
        Route::put('/reviews/{id}',    [ReviewController::class, 'update']);
        Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);

        // Administration — protégée par le middleware "admin" (gate unique).
        Route::prefix('admin')->middleware('admin')->group(function () {
            Route::get('/stats',                         [AdminController::class, 'stats']);
            Route::get('/orders',                        [AdminController::class, 'orders']);
            Route::patch('/orders/{order}/status',       [AdminController::class, 'updateOrderStatus']);
            // Validation des ordonnances par le pharmacien
            Route::get('/prescriptions/pending',            [PrescriptionController::class, 'pending']);
            Route::post('/prescriptions/{order}/approve',   [PrescriptionController::class, 'approve']);
            Route::post('/prescriptions/{order}/reject',    [PrescriptionController::class, 'reject']);
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
            
            // Produits Admin
            Route::apiResource('products', AdminProductController::class);
            Route::post('products/{product}/images', [AdminProductController::class, 'uploadImage']);

            // Promotions Admin
            Route::apiResource('promotions', AdminPromotionController::class);

            // Utilisateurs Admin
            Route::get('/users', [AdminController::class, 'users']);
            Route::post('/users', [AdminController::class, 'createUser']);
            Route::put('/users/{user}', [AdminController::class, 'updateUser']);
            Route::delete('/users/{user}', [AdminController::class, 'deleteUser']);

            // Rapports
            Route::get('/reports', [AdminController::class, 'reports']);

            // Notifications Admin
            Route::get('/notifications', [AdminController::class, 'notifications']);
            Route::post('/notifications', [AdminController::class, 'createNotification']);

            // Paramètres
            Route::get('/settings', [AdminController::class, 'settings']);
            Route::patch('/settings', [AdminController::class, 'updateSettings']);

            // Journal d'activité
            Route::get('/activity-log', [AdminController::class, 'activityLog']);
        });
    });
});

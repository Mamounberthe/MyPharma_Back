<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PharmacyControllerRefactored;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\OrderControllerRefactored;
use App\Http\Controllers\Api\OrderTrackingController;
use App\Http\Controllers\Api\PharmacyInvitationController;
use App\Http\Controllers\Api\PaymentController;
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
        Route::get('/invitations/validate/{token}', [PharmacyInvitationController::class, 'validateToken']);
    });

    // ---------------------------------------------------------------
    // Routes publiques — Pharmacies et Produits
    // ---------------------------------------------------------------
    Route::middleware('throttle:60,1')->group(function () {
        // Pharmacies (publiques)
        Route::get('/pharmacies',              [PharmacyControllerRefactored::class, 'index']);
        Route::get('/pharmacies/{id}',         [PharmacyControllerRefactored::class, 'show']);
        Route::get('/pharmacies/{id}/reviews', [PharmacyControllerRefactored::class, 'reviews']);

        // Produits (publiques en lecture seule)
        Route::get('/products',                    [ProductController::class, 'index']);
        Route::get('/products/{id}',               [ProductController::class, 'show']);
        Route::get('/search',                      [ProductController::class, 'search']);
        Route::get('/pharmacies/{id}/products',   [ProductController::class, 'pharmacyProducts']);
        Route::get('/products/stats',              [ProductController::class, 'stats']);
        Route::get('/products/popular',            [ProductController::class, 'popular']);
    });

    // ---------------------------------------------------------------
    // Routes protégées — 60 requêtes/minute par utilisateur
    // ---------------------------------------------------------------
    Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {

        // User
        Route::get('/user',    [AuthController::class, 'user']);
        Route::post('/push-token', [AuthController::class, 'updatePushToken']);
        Route::post('/logout', [AuthController::class, 'logout']);

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
        Route::get('/orders/{order}/tracking', [OrderTrackingController::class, 'getLocation']);
        Route::post('/orders/{order}/tracking', [OrderTrackingController::class, 'updateLocation']);
        Route::post('/orders/{order}/pay', [PaymentController::class, 'pay']);

        // Invitations
        Route::get('/invitations', [PharmacyInvitationController::class, 'index']);
        Route::post('/invitations', [PharmacyInvitationController::class, 'store']);
        
        // Avis
        Route::post('/reviews',        [ReviewController::class, 'store']);
        Route::put('/reviews/{id}',    [ReviewController::class, 'update']);
        Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);
    });
});

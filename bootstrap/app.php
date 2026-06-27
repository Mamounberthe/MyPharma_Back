<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Un seul middleware CORS — CorsMiddleware uniquement
        $middleware->prepend(\App\Http\Middleware\CorsMiddleware::class);

        // Retirer HandleCors natif de Laravel pour éviter les doublons d'en-têtes
        $middleware->remove(\Illuminate\Http\Middleware\HandleCors::class);

        // Alias d'autorisation : protège le groupe de routes admin.
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Ajouter les en-têtes CORS même sur les réponses d'erreur (500, 404, etc.)
        $exceptions->respond(function (\Illuminate\Http\Response|\Symfony\Component\HttpFoundation\Response $response, \Throwable $e, \Illuminate\Http\Request $request) {
            $origin = $request->header('Origin');
            $allowedOrigins = array_filter([
                'http://localhost:3001',
                env('APP_FRONTEND_URL') ? rtrim(env('APP_FRONTEND_URL'), '/') : null,
            ]);

            if ($origin && in_array($origin, $allowedOrigins, true)) {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
            }

            return $response;
        });
    })->create();
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
    private function getAllowedOrigins(): array
    {
        $origins = ['http://localhost:3001'];

        $frontendUrl = env('APP_FRONTEND_URL');
        if ($frontendUrl) {
            $origins[] = rtrim($frontendUrl, '/');
        }

        return $origins;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->header('Origin');
        $allowedOrigins = $this->getAllowedOrigins();

        if (!$origin || !in_array($origin, $allowedOrigins, true)) {
            if ($request->getMethod() === 'OPTIONS') {
                return response('', 204);
            }
            return $next($request);
        }

        $headers = [
            'Access-Control-Allow-Origin'      => $origin,
            'Access-Control-Allow-Methods'     => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers'     => 'Content-Type, Authorization, Accept, Origin, X-Requested-With',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Max-Age'           => '86400',
        ];

        if ($request->getMethod() === 'OPTIONS') {
            return response('', 200, $headers);
        }

        $response = $next($request);

        foreach ($headers as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }
}

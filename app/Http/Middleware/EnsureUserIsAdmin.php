<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Autorise uniquement les utilisateurs ayant le rôle "admin".
     *
     * Gate unique au niveau du routage : remplace les vérifications manuelles
     * disséminées dans les contrôleurs (un oubli y exposait un endpoint).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isAdmin()) {
            abort(403, 'Accès réservé aux administrateurs.');
        }

        return $next($request);
    }
}

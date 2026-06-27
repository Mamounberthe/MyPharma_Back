<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerSqliteMathFunctions();
    }

    /**
     * SQLite (utilisé en tests/local) ne fournit pas les fonctions
     * trigonométriques requises par la formule de Haversine (acos, cos, sin,
     * radians). PostgreSQL (production) les possède nativement. On les
     * enregistre ici pour que le MÊME SQL fonctionne dans les deux environnements.
     */
    private function registerSqliteMathFunctions(): void
    {
        $default = config('database.default');

        // On ne force pas de connexion en production (PostgreSQL) : on lit le
        // driver depuis la config sans ouvrir de connexion inutile.
        if (config("database.connections.{$default}.driver") !== 'sqlite') {
            return;
        }

        try {
            $pdo = DB::connection()->getPdo();
        } catch (\Throwable $e) {
            return; // pas de base disponible (ex. avant migration) : on ignore.
        }

        if (! method_exists($pdo, 'sqliteCreateFunction')) {
            return;
        }

        $pdo->sqliteCreateFunction('acos', 'acos', 1);
        $pdo->sqliteCreateFunction('asin', 'asin', 1);
        $pdo->sqliteCreateFunction('cos', 'cos', 1);
        $pdo->sqliteCreateFunction('sin', 'sin', 1);
        $pdo->sqliteCreateFunction('sqrt', 'sqrt', 1);
        $pdo->sqliteCreateFunction('radians', 'deg2rad', 1);
        $pdo->sqliteCreateFunction('degrees', 'rad2deg', 1);
        $pdo->sqliteCreateFunction('pi', 'pi', 0);
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Active l'extension PostgreSQL "unaccent" pour permettre une recherche
     * insensible aux accents (voir App\Models\Product::scopeSearch).
     *
     * Ne s'applique qu'à PostgreSQL. En cas de privilèges insuffisants, on
     * n'interrompt PAS le déploiement : la recherche retombe automatiquement
     * sur une comparaison insensible à la casse uniquement.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');
        } catch (\Throwable $e) {
            Log::warning('Extension unaccent non installée : ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        // On ne supprime pas l'extension : elle peut servir ailleurs et sa
        // suppression n'apporte rien.
    }
};

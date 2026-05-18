<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pharmacies', function (Blueprint $table) {
            // Index pour les requêtes géographiques
            $table->index(['latitude', 'longitude'], 'pharmacies_location_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            // Index pour le filtrage par statut
            $table->index('status', 'orders_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pharmacies', function (Blueprint $table) {
            $table->dropIndex('pharmacies_location_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_status_index');
        });
    }
};

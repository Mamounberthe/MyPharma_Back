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
            $table->boolean('is_partner')->default(true)->after('id');
            $table->string('google_place_id')->nullable()->unique()->after('delivery_available');
            $table->string('osm_id')->nullable()->unique()->after('google_place_id');
            $table->boolean('is_on_call')->default(false)->after('osm_id');
            $table->json('opening_hours')->nullable()->after('is_on_call');
            $table->string('status')->default('active')->after('opening_hours');
            
            // Index pour la recherche géographique si nécessaire (si non déjà présent)
            // Note: Les index latitude/longitude sont souvent déjà là, mais on peut s'en assurer
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pharmacies', function (Blueprint $table) {
            $table->dropColumn([
                'is_partner',
                'google_place_id',
                'osm_id',
                'is_on_call',
                'opening_hours',
                'status'
            ]);
        });
    }
};

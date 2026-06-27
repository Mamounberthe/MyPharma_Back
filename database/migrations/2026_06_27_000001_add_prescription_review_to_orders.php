<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Cycle de validation de l'ordonnance par un pharmacien.
            // Valeurs : not_required | pending_review | approved | rejected
            $table->string('prescription_status')
                ->default('not_required')
                ->after('prescription_url');

            $table->foreignId('prescription_reviewed_by')
                ->nullable()
                ->after('prescription_status')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('prescription_reviewed_at')
                ->nullable()
                ->after('prescription_reviewed_by');

            $table->text('prescription_rejection_reason')
                ->nullable()
                ->after('prescription_reviewed_at');

            // Accès rapide à la file d'attente du pharmacien.
            $table->index('prescription_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prescription_reviewed_by');
            $table->dropIndex(['prescription_status']);
            $table->dropColumn([
                'prescription_status',
                'prescription_reviewed_at',
                'prescription_rejection_reason',
            ]);
        });
    }
};

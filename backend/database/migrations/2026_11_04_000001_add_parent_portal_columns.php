<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Portail parents (docs/PRODUCT_ARCHITECTURE.md §10).
 *
 * Le compte d'un parent est relié explicitement à sa fiche « responsable »
 * (guardians.user_id) par l'école : jamais déduit du seul numéro de
 * téléphone, qui peut être réattribué par l'opérateur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guardians', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            $table->unique(['tenant_id', 'user_id']);
        });

        // Justificatifs déposés depuis l'app : identifiant public et examen
        // par la vie scolaire.
        Schema::table('absence_justifications', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
            $table->foreignId('reviewed_by')->nullable()->after('recorded_by')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });

        DB::statement('UPDATE absence_justifications SET uuid = gen_random_uuid() WHERE uuid IS NULL');

        Schema::table('absence_justifications', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('absence_justifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['uuid', 'reviewed_at']);
        });

        Schema::table('guardians', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'user_id']);
            $table->dropConstrainedForeignId('user_id');
        });
    }
};

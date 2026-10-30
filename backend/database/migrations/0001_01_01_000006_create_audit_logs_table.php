<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal d'audit des actions sensibles (docs/PRODUCT_ARCHITECTURE.md §14).
 * Écriture seule : aucune ligne n'est modifiée ni supprimée par
 * l'application. tenant_id est nul pour les actions de plateforme.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('actor');
            $table->string('action', 100);
            $table->nullableMorphs('subject');
            $table->jsonb('metadata')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

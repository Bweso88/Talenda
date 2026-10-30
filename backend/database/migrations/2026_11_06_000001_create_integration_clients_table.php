<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Applications partenaires autorisées à lire les données d'une école
 * (d'abord Scolia, docs/PRODUCT_ARCHITECTURE.md §18). Une clé par école et
 * par application ; seule son empreinte est stockée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_clients', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->char('token_hash', 64)->unique();
            // Début de la clé, pour la reconnaître dans l'écran (jamais la clé entière).
            $table->string('token_prefix', 12);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_clients');
    }
};

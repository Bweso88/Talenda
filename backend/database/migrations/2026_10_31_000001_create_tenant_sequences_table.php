<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compteurs séquentiels par école (matricules, puis numéros de reçus et de
 * factures) : sans trou ni doublon, même avec des saisies simultanées
 * (verrou de ligne, voir SequenceGenerator).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->unsignedBigInteger('last_value')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_sequences');
    }
};

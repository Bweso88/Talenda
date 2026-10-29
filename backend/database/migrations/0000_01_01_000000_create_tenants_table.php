<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Écoles clientes (docs/PRODUCT_ARCHITECTURE.md §7, §8.1). Table de
 * plateforme : elle ne porte pas de tenant_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            // Utilisé dans l'URL du back-office : /ecole/{slug}.
            $table->string('slug', 63)->unique();
            $table->char('country_code', 2);
            $table->char('currency', 3);
            $table->string('timezone', 64);
            $table->string('locale', 8);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};

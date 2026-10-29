<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Années scolaires et périodes (trimestres) — docs/PRODUCT_ARCHITECTURE.md §8.2.
 *
 * Convention des tables d'école : unique (tenant_id, id) sur chaque table
 * référencée, et clés étrangères composites (tenant_id, …_id) dans les
 * tables qui la référencent. La base refuse ainsi elle-même tout lien entre
 * deux écoles (§7, point 5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_years', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 20);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 20)->default('draft');
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'name']);
        });

        DB::statement('ALTER TABLE school_years ADD CONSTRAINT school_years_dates CHECK (ends_on > starts_on)');
        // Une seule année ouverte par école.
        DB::statement("CREATE UNIQUE INDEX school_years_one_open_per_tenant ON school_years (tenant_id) WHERE status = 'open'");

        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('school_year_id');
            $table->string('name', 50);
            $table->unsignedSmallInteger('position');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 20)->default('open');
            $table->timestamps();

            $table->foreign(['tenant_id', 'school_year_id'])
                ->references(['tenant_id', 'id'])->on('school_years')
                ->cascadeOnDelete();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['school_year_id', 'position']);
        });

        DB::statement('ALTER TABLE terms ADD CONSTRAINT terms_dates CHECK (ends_on > starts_on)');
    }

    public function down(): void
    {
        Schema::dropIfExists('terms');
        Schema::dropIfExists('school_years');
    }
};

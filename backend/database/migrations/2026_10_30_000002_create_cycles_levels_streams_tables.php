<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cycles, niveaux et séries de l'école — docs/PRODUCT_ARCHITECTURE.md §8.2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('levels', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('cycle_id');
            $table->string('code', 20);
            $table->string('name', 100);
            $table->unsignedSmallInteger('position')->default(0);
            // Niveau proposé au passage en classe supérieure.
            $table->unsignedBigInteger('next_level_id')->nullable();
            // Examen national passé en fin de niveau (CEPE, BEPC, Bac…).
            $table->string('exam_name', 50)->nullable();
            $table->timestamps();

            $table->foreign(['tenant_id', 'cycle_id'])
                ->references(['tenant_id', 'id'])->on('cycles')
                ->restrictOnDelete();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'code']);
        });

        // SET NULL limité à next_level_id (PostgreSQL 15+) : tenant_id ne
        // doit jamais être vidé.
        DB::statement('ALTER TABLE levels ADD CONSTRAINT levels_next_level_foreign
            FOREIGN KEY (tenant_id, next_level_id) REFERENCES levels (tenant_id, id)
            ON DELETE SET NULL (next_level_id)');

        Schema::create('streams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('streams');
        Schema::dropIfExists('levels');
        Schema::dropIfExists('cycles');
    }
};

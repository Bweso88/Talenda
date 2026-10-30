<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tarifs de l'école : types de frais, grille par année et par niveau,
 * plans de paiement (mensuel, trimestriel…) — docs/PRODUCT_ARCHITECTURE.md §8.4.
 *
 * Montants : entiers en francs (XAF n'a pas de centimes), jamais de float.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 100);
            // upfront : dû à l'inscription ; spread : réparti selon le plan.
            $table->string('billing', 10);
            // all, new, re_enrollment.
            $table->string('applies_to', 15)->default('all');
            $table->boolean('is_optional')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'code']);
        });

        DB::statement("ALTER TABLE fee_types ADD CONSTRAINT fee_types_billing CHECK (billing IN ('upfront', 'spread'))");
        DB::statement("ALTER TABLE fee_types ADD CONSTRAINT fee_types_applies_to CHECK (applies_to IN ('all', 'new', 're_enrollment'))");

        Schema::create('fee_amounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('school_year_id');
            $table->unsignedBigInteger('level_id');
            $table->unsignedBigInteger('fee_type_id');
            $table->bigInteger('amount');
            $table->timestamps();

            $table->foreign(['tenant_id', 'school_year_id'])->references(['tenant_id', 'id'])->on('school_years')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'level_id'])->references(['tenant_id', 'id'])->on('levels')->restrictOnDelete();
            $table->foreign(['tenant_id', 'fee_type_id'])->references(['tenant_id', 'id'])->on('fee_types')->restrictOnDelete();
            $table->unique(['school_year_id', 'level_id', 'fee_type_id']);
        });

        DB::statement('ALTER TABLE fee_amounts ADD CONSTRAINT fee_amounts_positive CHECK (amount > 0)');

        Schema::create('payment_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('school_year_id');
            $table->string('name', 50);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->foreign(['tenant_id', 'school_year_id'])->references(['tenant_id', 'id'])->on('school_years')->cascadeOnDelete();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['school_year_id', 'name']);
        });

        DB::statement('CREATE UNIQUE INDEX payment_plans_one_default ON payment_plans (school_year_id) WHERE is_default');

        Schema::create('payment_plan_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('payment_plan_id');
            $table->unsignedSmallInteger('position');
            $table->string('label', 50);
            $table->date('due_on');
            // Part de la scolarité (frais « spread ») due à cette échéance.
            $table->decimal('percentage', 5, 2);
            $table->timestamps();

            $table->foreign(['tenant_id', 'payment_plan_id'])->references(['tenant_id', 'id'])->on('payment_plans')->cascadeOnDelete();
            $table->unique(['payment_plan_id', 'position']);
        });

        DB::statement('ALTER TABLE payment_plan_installments ADD CONSTRAINT payment_plan_installments_percentage CHECK (percentage > 0 AND percentage <= 100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_plan_installments');
        Schema::dropIfExists('payment_plans');
        Schema::dropIfExists('fee_amounts');
        Schema::dropIfExists('fee_types');
    }
};

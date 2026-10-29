<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inscriptions : élève × année × classe — docs/PRODUCT_ARCHITECTURE.md §8.3.
 * Une ligne par année : l'historique de scolarité d'un élève se lit ici.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('school_year_id');
            $table->unsignedBigInteger('school_class_id');
            $table->string('type', 20);
            $table->string('status', 20);
            $table->date('enrolled_on');
            // Pièces du dossier fournies (codes de config/talenda.php).
            $table->jsonb('documents')->default('[]');
            $table->date('left_on')->nullable();
            $table->string('leaving_reason')->nullable();
            $table->string('destination_school')->nullable();
            // Décision de fin d'année (renseignée par le module Évaluations).
            $table->string('decision', 20)->nullable();
            $table->timestamps();

            $table->foreign(['tenant_id', 'student_id'])
                ->references(['tenant_id', 'id'])->on('students')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'school_year_id'])
                ->references(['tenant_id', 'id'])->on('school_years')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'school_class_id'])
                ->references(['tenant_id', 'id'])->on('school_classes')
                ->restrictOnDelete();

            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'school_class_id', 'status']);
            $table->index(['tenant_id', 'student_id']);
        });

        // Une seule inscription en cours par élève et par année.
        DB::statement("CREATE UNIQUE INDEX enrollments_one_active_per_year
            ON enrollments (student_id, school_year_id)
            WHERE status IN ('pre_registered', 'enrolled')");

        // La classe doit appartenir à l'année de l'inscription.
        DB::statement('ALTER TABLE school_classes ADD CONSTRAINT school_classes_year_unique UNIQUE (tenant_id, school_year_id, id)');
        DB::statement('ALTER TABLE enrollments ADD CONSTRAINT enrollments_class_in_year_foreign
            FOREIGN KEY (tenant_id, school_year_id, school_class_id)
            REFERENCES school_classes (tenant_id, school_year_id, id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');

        DB::statement('ALTER TABLE school_classes DROP CONSTRAINT school_classes_year_unique');
    }
};

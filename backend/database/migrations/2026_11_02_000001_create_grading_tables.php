<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Évaluations et notes — docs/PRODUCT_ARCHITECTURE.md §8.5.
 *
 * Notes en numeric (jamais de float). Une note est saisie sur le barème de
 * l'évaluation (ex. /10) et ramenée sur 20 au calcul des moyennes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('max_score')->default(20);
            $table->decimal('passing_average', 5, 2)->default(10);
            // ignore : absence non comptée ; zero : absence = 0.
            $table->string('absence_policy', 10)->default('ignore');
            // [{name, min}] du plus haut au plus bas seuil.
            $table->jsonb('distinctions')->default('[]');
            $table->timestamps();
        });

        DB::statement("ALTER TABLE grading_settings ADD CONSTRAINT grading_settings_absence CHECK (absence_policy IN ('ignore', 'zero'))");

        Schema::create('assessment_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 100);
            $table->decimal('weight', 5, 2)->default(1);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'code']);
        });

        DB::statement('ALTER TABLE assessment_types ADD CONSTRAINT assessment_types_weight CHECK (weight > 0)');

        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('class_subject_id');
            $table->unsignedBigInteger('term_id');
            $table->unsignedBigInteger('assessment_type_id');
            $table->string('title', 150);
            $table->date('held_on');
            $table->decimal('max_score', 5, 2)->default(20);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign(['tenant_id', 'class_subject_id'])->references(['tenant_id', 'id'])->on('class_subjects')->restrictOnDelete();
            $table->foreign(['tenant_id', 'term_id'])->references(['tenant_id', 'id'])->on('terms')->restrictOnDelete();
            $table->foreign(['tenant_id', 'assessment_type_id'])->references(['tenant_id', 'id'])->on('assessment_types')->restrictOnDelete();
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'term_id', 'class_subject_id']);
        });

        DB::statement('ALTER TABLE assessments ADD CONSTRAINT assessments_max_score CHECK (max_score > 0)');

        Schema::create('scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('assessment_id');
            $table->unsignedBigInteger('enrollment_id');
            $table->decimal('value', 5, 2)->nullable();
            // present, absent, exempted (dispensé).
            $table->string('status', 10)->default('present');
            $table->string('comment')->nullable();
            $table->timestamps();

            $table->foreign(['tenant_id', 'assessment_id'])->references(['tenant_id', 'id'])->on('assessments')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'enrollment_id'])->references(['tenant_id', 'id'])->on('enrollments')->restrictOnDelete();
            $table->unique(['assessment_id', 'enrollment_id']);
        });

        DB::statement("ALTER TABLE scores ADD CONSTRAINT scores_status CHECK (status IN ('present', 'absent', 'exempted'))");
        DB::statement("ALTER TABLE scores ADD CONSTRAINT scores_value CHECK (
            (status = 'present' AND value IS NOT NULL AND value >= 0)
            OR (status <> 'present' AND value IS NULL))");

        // Appréciations : par matière (class_subject_id renseigné, enseignant)
        // ou générale (class_subject_id nul, conseil de classe).
        Schema::create('term_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('term_id');
            $table->unsignedBigInteger('enrollment_id');
            $table->unsignedBigInteger('class_subject_id')->nullable();
            $table->string('body', 500);
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign(['tenant_id', 'term_id'])->references(['tenant_id', 'id'])->on('terms')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'enrollment_id'])->references(['tenant_id', 'id'])->on('enrollments')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'class_subject_id'])->references(['tenant_id', 'id'])->on('class_subjects')->cascadeOnDelete();
        });

        DB::statement('CREATE UNIQUE INDEX term_comments_unique ON term_comments (term_id, enrollment_id, class_subject_id) NULLS NOT DISTINCT');
    }

    public function down(): void
    {
        Schema::dropIfExists('term_comments');
        Schema::dropIfExists('scores');
        Schema::dropIfExists('assessments');
        Schema::dropIfExists('assessment_types');
        Schema::dropIfExists('grading_settings');
    }
};

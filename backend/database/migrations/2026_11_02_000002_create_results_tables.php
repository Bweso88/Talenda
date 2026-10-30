<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Résultats **figés** à la publication d'un trimestre (§8.5) : ce qui est
 * imprimé sur le bulletin est ce qui est stocké ici, jamais recalculé à
 * partir des notes. Libellés (matière, enseignant, classe) recopiés pour
 * que le bulletin reste identique même si la structure change ensuite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('term_results', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('term_id');
            $table->unsignedBigInteger('enrollment_id');
            $table->string('class_name', 50);
            $table->decimal('average', 5, 2)->nullable();
            $table->unsignedSmallInteger('rank')->nullable();
            $table->boolean('rank_tied')->default(false);
            $table->unsignedSmallInteger('class_size');
            $table->decimal('class_average', 5, 2)->nullable();
            $table->decimal('class_highest', 5, 2)->nullable();
            $table->decimal('class_lowest', 5, 2)->nullable();
            $table->decimal('total_coefficient', 6, 2);
            $table->decimal('total_points', 8, 2)->nullable();
            $table->string('distinction', 50)->nullable();
            $table->string('council_comment', 500)->nullable();
            $table->string('head_teacher_name')->nullable();
            $table->timestamp('published_at');
            $table->timestamps();

            $table->foreign(['tenant_id', 'term_id'])->references(['tenant_id', 'id'])->on('terms')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'enrollment_id'])->references(['tenant_id', 'id'])->on('enrollments')->restrictOnDelete();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['term_id', 'enrollment_id']);
        });

        Schema::create('term_subject_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('term_result_id');
            $table->unsignedBigInteger('class_subject_id')->nullable();
            $table->unsignedSmallInteger('position');
            $table->string('subject_name', 100);
            $table->string('teacher_name')->nullable();
            $table->decimal('coefficient', 4, 2);
            $table->decimal('average', 5, 2)->nullable();
            $table->unsignedSmallInteger('rank')->nullable();
            $table->decimal('class_average', 5, 2)->nullable();
            $table->decimal('class_highest', 5, 2)->nullable();
            $table->decimal('class_lowest', 5, 2)->nullable();
            $table->string('comment', 500)->nullable();
            $table->timestamps();

            $table->foreign(['tenant_id', 'term_result_id'])->references(['tenant_id', 'id'])->on('term_results')->cascadeOnDelete();
            $table->index(['tenant_id', 'term_result_id']);
        });

        Schema::create('annual_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('enrollment_id');
            $table->decimal('average', 5, 2)->nullable();
            $table->unsignedSmallInteger('rank')->nullable();
            $table->boolean('rank_tied')->default(false);
            $table->unsignedSmallInteger('class_size');
            $table->timestamps();

            $table->foreign(['tenant_id', 'enrollment_id'])->references(['tenant_id', 'id'])->on('enrollments')->cascadeOnDelete();
            $table->unique('enrollment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annual_results');
        Schema::dropIfExists('term_subject_results');
        Schema::dropIfExists('term_results');
    }
};

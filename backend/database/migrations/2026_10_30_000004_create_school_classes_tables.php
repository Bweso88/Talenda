<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Classes d'une année et matières enseignées dans chaque classe —
 * docs/PRODUCT_ARCHITECTURE.md §8.2.
 *
 * class_subjects porte le coefficient **et** l'enseignant de la matière
 * dans la classe : un enseignant par matière et par classe. Cela remplace
 * la table teaching_assignments prévue (voir le journal des décisions).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('school_year_id');
            $table->unsignedBigInteger('level_id');
            $table->unsignedBigInteger('stream_id')->nullable();
            $table->string('name', 50);
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->string('room', 50)->nullable();
            $table->unsignedBigInteger('head_teacher_id')->nullable();
            $table->timestamps();

            $table->foreign(['tenant_id', 'school_year_id'])
                ->references(['tenant_id', 'id'])->on('school_years')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'level_id'])
                ->references(['tenant_id', 'id'])->on('levels')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'stream_id'])
                ->references(['tenant_id', 'id'])->on('streams')
                ->restrictOnDelete();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['school_year_id', 'name']);
            $table->index(['tenant_id', 'school_year_id']);
        });

        DB::statement('ALTER TABLE school_classes ADD CONSTRAINT school_classes_capacity CHECK (capacity IS NULL OR capacity > 0)');
        // SET NULL limité à la colonne de l'enseignant (PostgreSQL 15+) :
        // tenant_id ne doit jamais être vidé.
        DB::statement('ALTER TABLE school_classes ADD CONSTRAINT school_classes_head_teacher_foreign
            FOREIGN KEY (tenant_id, head_teacher_id) REFERENCES staff_members (tenant_id, id)
            ON DELETE SET NULL (head_teacher_id)');

        Schema::create('class_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('school_class_id');
            $table->unsignedBigInteger('subject_id');
            $table->decimal('coefficient', 4, 2);
            $table->unsignedBigInteger('teacher_id')->nullable();
            $table->timestamps();

            $table->foreign(['tenant_id', 'school_class_id'])
                ->references(['tenant_id', 'id'])->on('school_classes')
                ->cascadeOnDelete();
            $table->foreign(['tenant_id', 'subject_id'])
                ->references(['tenant_id', 'id'])->on('subjects')
                ->restrictOnDelete();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['school_class_id', 'subject_id']);
            $table->index(['tenant_id', 'teacher_id']);
        });

        DB::statement('ALTER TABLE class_subjects ADD CONSTRAINT class_subjects_coefficient CHECK (coefficient > 0)');
        DB::statement('ALTER TABLE class_subjects ADD CONSTRAINT class_subjects_teacher_foreign
            FOREIGN KEY (tenant_id, teacher_id) REFERENCES staff_members (tenant_id, id)
            ON DELETE SET NULL (teacher_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('class_subjects');
        Schema::dropIfExists('school_classes');
    }
};

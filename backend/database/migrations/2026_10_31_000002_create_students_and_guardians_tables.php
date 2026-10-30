<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Élèves (identité permanente, d'une année à l'autre) et responsables —
 * docs/PRODUCT_ARCHITECTURE.md §8.3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('registration_number', 30);
            $table->string('last_name', 100);
            $table->string('first_names', 150);
            $table->char('gender', 1);
            $table->date('birth_date');
            $table->string('birth_place', 100)->nullable();
            $table->char('nationality', 2)->nullable();
            $table->string('address')->nullable();
            $table->string('previous_school')->nullable();
            // Uniquement ce qui est utile en cas d'urgence (allergie,
            // traitement) : pas de dossier médical (§14).
            $table->string('medical_notes', 500)->nullable();
            $table->string('photo_path')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'registration_number']);
            $table->index(['tenant_id', 'last_name', 'first_names']);
        });

        DB::statement("ALTER TABLE students ADD CONSTRAINT students_gender CHECK (gender IN ('F', 'M'))");

        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('last_name', 100);
            $table->string('first_names', 150)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('profession', 100)->nullable();
            $table->string('address')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            // Un même parent (même téléphone) n'est saisi qu'une fois, même
            // avec plusieurs enfants dans l'école.
            $table->unique(['tenant_id', 'phone']);
        });

        Schema::create('student_guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('guardian_id');
            $table->string('relationship', 20);
            $table->boolean('is_primary_contact')->default(false);
            $table->boolean('is_financially_responsible')->default(false);
            $table->timestamps();

            $table->foreign(['tenant_id', 'student_id'])
                ->references(['tenant_id', 'id'])->on('students')
                ->cascadeOnDelete();
            $table->foreign(['tenant_id', 'guardian_id'])
                ->references(['tenant_id', 'id'])->on('guardians')
                ->cascadeOnDelete();
            $table->unique(['student_id', 'guardian_id']);
            $table->index(['tenant_id', 'guardian_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_guardians');
        Schema::dropIfExists('guardians');
        Schema::dropIfExists('students');
    }
};

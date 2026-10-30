<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vie scolaire — docs/PRODUCT_ARCHITECTURE.md §8.6.
 *
 * Un appel (attendance_session) est fait par classe, par jour et par
 * créneau. Seuls les absents et les retardataires sont enregistrés : un
 * appel sans ligne veut dire « tout le monde était là ». Le caractère
 * justifié d'une absence n'est pas stocké : il découle des justificatifs
 * acceptés qui couvrent sa date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'name']);
        });

        DB::statement('ALTER TABLE attendance_slots ADD CONSTRAINT attendance_slots_times CHECK (ends_at > starts_at)');

        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('school_class_id');
            $table->unsignedBigInteger('attendance_slot_id');
            $table->date('held_on');
            // Durée du créneau au moment de l'appel : les heures d'absence
            // passées ne changent pas si l'école modifie ses horaires.
            $table->unsignedSmallInteger('duration_minutes');
            $table->foreignId('taken_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign(['tenant_id', 'school_class_id'])->references(['tenant_id', 'id'])->on('school_classes')->restrictOnDelete();
            $table->foreign(['tenant_id', 'attendance_slot_id'])->references(['tenant_id', 'id'])->on('attendance_slots')->restrictOnDelete();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['school_class_id', 'held_on', 'attendance_slot_id']);
            $table->index(['tenant_id', 'held_on']);
        });

        DB::statement('ALTER TABLE attendance_sessions ADD CONSTRAINT attendance_sessions_duration CHECK (duration_minutes > 0)');

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('attendance_session_id');
            $table->unsignedBigInteger('enrollment_id');
            // absent ou late (retard).
            $table->string('status', 10);
            $table->unsignedSmallInteger('late_minutes')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->foreign(['tenant_id', 'attendance_session_id'])->references(['tenant_id', 'id'])->on('attendance_sessions')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'enrollment_id'])->references(['tenant_id', 'id'])->on('enrollments')->restrictOnDelete();
            $table->unique(['attendance_session_id', 'enrollment_id']);
            $table->index(['tenant_id', 'enrollment_id']);
        });

        DB::statement("ALTER TABLE attendance_records ADD CONSTRAINT attendance_records_status CHECK (
            (status = 'absent' AND late_minutes IS NULL)
            OR (status = 'late' AND (late_minutes IS NULL OR late_minutes > 0)))");

        Schema::create('absence_justifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('enrollment_id');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('reason', 20);
            $table->string('comment', 500)->nullable();
            // pending : déposé par un parent (app mobile), à examiner.
            $table->string('status', 10)->default('accepted');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign(['tenant_id', 'enrollment_id'])->references(['tenant_id', 'id'])->on('enrollments')->cascadeOnDelete();
            $table->index(['tenant_id', 'enrollment_id', 'starts_on']);
        });

        DB::statement('ALTER TABLE absence_justifications ADD CONSTRAINT absence_justifications_dates CHECK (ends_on >= starts_on)');
        DB::statement("ALTER TABLE absence_justifications ADD CONSTRAINT absence_justifications_status CHECK (status IN ('pending', 'accepted', 'rejected'))");

        Schema::create('disciplinary_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('enrollment_id');
            $table->date('occurred_on');
            $table->string('description', 1000);
            $table->string('sanction', 20);
            $table->unsignedSmallInteger('suspension_days')->nullable();
            $table->boolean('parents_informed')->default(false);
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign(['tenant_id', 'enrollment_id'])->references(['tenant_id', 'id'])->on('enrollments')->restrictOnDelete();
            $table->index(['tenant_id', 'enrollment_id']);
        });

        DB::statement("ALTER TABLE disciplinary_incidents ADD CONSTRAINT disciplinary_incidents_suspension CHECK (
            (sanction = 'suspension' AND suspension_days > 0)
            OR (sanction <> 'suspension' AND suspension_days IS NULL))");

        // Absences et retards figés sur le bulletin, comme les moyennes.
        Schema::table('term_results', function (Blueprint $table) {
            $table->unsignedInteger('absence_minutes')->default(0);
            $table->unsignedInteger('justified_absence_minutes')->default(0);
            $table->unsignedSmallInteger('late_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('term_results', function (Blueprint $table) {
            $table->dropColumn(['absence_minutes', 'justified_absence_minutes', 'late_count']);
        });
        Schema::dropIfExists('disciplinary_incidents');
        Schema::dropIfExists('absence_justifications');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('attendance_sessions');
        Schema::dropIfExists('attendance_slots');
    }
};

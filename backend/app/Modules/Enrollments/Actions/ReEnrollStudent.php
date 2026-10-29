<?php

declare(strict_types=1);

namespace App\Modules\Enrollments\Actions;

use App\Modules\Academic\Models\Level;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Enrollments\Enums\EnrollmentStatus;
use App\Modules\Enrollments\Enums\EnrollmentType;
use App\Modules\Enrollments\Enums\YearEndDecision;
use App\Modules\Enrollments\Events\StudentEnrolled;
use App\Modules\Enrollments\Models\Enrollment;
use App\Modules\Enrollments\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Réinscription d'un élève déjà connu de l'école dans une classe d'une
 * nouvelle année (docs/PRODUCT_ARCHITECTURE.md §9).
 */
final class ReEnrollStudent
{
    public function __construct(
        private readonly EnsureSeatAvailable $ensureSeatAvailable,
        private readonly RecordAudit $recordAudit,
    ) {}

    /**
     * Niveau proposé à la réinscription selon la décision de fin d'année :
     * même niveau si l'élève redouble, niveau suivant sinon.
     */
    public static function suggestedLevel(Enrollment $previous): ?Level
    {
        $level = $previous->schoolClass->level;

        return match ($previous->decision) {
            YearEndDecision::Repeat => $level,
            YearEndDecision::Excluded, YearEndDecision::Graduated => null,
            default => $level->nextLevel,
        };
    }

    public function handle(
        Student $student,
        SchoolClass $schoolClass,
        EnrollmentStatus $status = EnrollmentStatus::Enrolled,
        ?Model $actor = null,
    ): Enrollment {
        $this->ensureSeatAvailable->handle($schoolClass);

        $alreadyEnrolled = $student->enrollments()
            ->active()
            ->where('school_year_id', $schoolClass->school_year_id)
            ->with('schoolClass')
            ->first();

        if ($alreadyEnrolled !== null) {
            throw new BusinessRuleViolation("{$student->full_name} est déjà inscrit(e) en {$alreadyEnrolled->schoolClass->name} cette année.");
        }

        $excluded = $student->enrollments()->where('decision', YearEndDecision::Excluded)->exists();

        if ($excluded) {
            throw new BusinessRuleViolation("{$student->full_name} a été exclu(e) de l'école : réinscription impossible.");
        }

        return DB::transaction(function () use ($student, $schoolClass, $status, $actor): Enrollment {
            $enrollment = Enrollment::create([
                'student_id' => $student->id,
                'school_year_id' => $schoolClass->school_year_id,
                'school_class_id' => $schoolClass->id,
                'type' => EnrollmentType::ReEnrollment,
                'status' => $status,
                'enrolled_on' => now(),
            ]);

            $this->recordAudit->handle('enrollment.re_enrolled', actor: $actor, subject: $enrollment, metadata: [
                'student' => $student->registration_number,
                'class' => $schoolClass->name,
            ]);

            StudentEnrolled::dispatch($enrollment);

            return $enrollment;
        });
    }
}

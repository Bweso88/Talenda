<?php

declare(strict_types=1);

namespace App\Modules\Enrollments\Actions;

use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Core\Support\SequenceGenerator;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Enrollments\Enums\EnrollmentStatus;
use App\Modules\Enrollments\Enums\EnrollmentType;
use App\Modules\Enrollments\Events\StudentEnrolled;
use App\Modules\Enrollments\Models\Enrollment;
use App\Modules\Enrollments\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Inscription d'un nouvel élève (docs/PRODUCT_ARCHITECTURE.md §9) :
 * fiche élève avec matricule, responsables (réutilisés si le téléphone est
 * déjà connu, cas des fratries), inscription dans la classe.
 *
 * @phpstan-type StudentData array{last_name: string, first_names: string, gender: string, birth_date: string|\DateTimeInterface, birth_place?: string|null, nationality?: string|null, address?: string|null, previous_school?: string|null, medical_notes?: string|null, photo_path?: string|null}
 * @phpstan-type GuardianData array{last_name: string, first_names?: string|null, phone?: string|null, email?: string|null, profession?: string|null, address?: string|null, relationship: string, is_primary_contact?: bool, is_financially_responsible?: bool}
 */
final class EnrollNewStudent
{
    public function __construct(
        private readonly EnsureSeatAvailable $ensureSeatAvailable,
        private readonly SequenceGenerator $sequenceGenerator,
        private readonly RecordAudit $recordAudit,
        private readonly LinkGuardian $linkGuardian,
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @param  StudentData  $studentData
     * @param  list<GuardianData>  $guardians
     * @param  list<string>  $documents  pièces du dossier fournies
     */
    public function handle(
        array $studentData,
        array $guardians,
        SchoolClass $schoolClass,
        EnrollmentStatus $status = EnrollmentStatus::Enrolled,
        array $documents = [],
        bool $allowPossibleDuplicate = false,
        ?Model $actor = null,
    ): Enrollment {
        $this->ensureSeatAvailable->handle($schoolClass);

        if (! $allowPossibleDuplicate) {
            $this->guardAgainstDuplicate($studentData);
        }

        return DB::transaction(function () use ($studentData, $guardians, $schoolClass, $status, $documents, $actor): Enrollment {
            $student = Student::create([
                // Nationalité par défaut : celle du pays de l'école.
                'nationality' => $this->tenantContext->get()?->country_code,
                ...array_filter($studentData, fn (mixed $value): bool => $value !== null),
                'registration_number' => $this->nextRegistrationNumber($schoolClass),
            ]);

            foreach ($guardians as $guardian) {
                $this->linkGuardian->handle($student, $guardian);
            }

            $enrollment = Enrollment::create([
                'student_id' => $student->id,
                'school_year_id' => $schoolClass->school_year_id,
                'school_class_id' => $schoolClass->id,
                'type' => EnrollmentType::New,
                'status' => $status,
                'enrolled_on' => now(),
                'documents' => $documents,
            ]);

            $this->recordAudit->handle('enrollment.created', actor: $actor, subject: $enrollment, metadata: [
                'student' => $student->registration_number,
                'class' => $schoolClass->name,
            ]);

            StudentEnrolled::dispatch($enrollment);

            return $enrollment;
        });
    }

    /**
     * Même nom, mêmes prénoms, même date de naissance : très probablement
     * un élève déjà connu, à réinscrire plutôt qu'à recréer.
     *
     * @param  StudentData  $studentData
     */
    private function guardAgainstDuplicate(array $studentData): void
    {
        $existing = Student::query()
            ->where('last_name', mb_strtoupper(trim($studentData['last_name'])))
            ->whereRaw('lower(first_names) = ?', [mb_strtolower(trim($studentData['first_names']))])
            ->whereDate('birth_date', Carbon::parse($studentData['birth_date']))
            ->first();

        if ($existing !== null) {
            throw new BusinessRuleViolation(
                "{$existing->full_name}, né(e) le {$existing->birth_date->format('d/m/Y')}, existe déjà (matricule {$existing->registration_number}). Utilisez la réinscription.",
            );
        }
    }

    /**
     * Matricule « AA-NNNN » : AA = année de la première inscription,
     * NNNN = numéro d'ordre dans l'école pour cette année.
     */
    private function nextRegistrationNumber(SchoolClass $schoolClass): string
    {
        $year = $schoolClass->schoolYear->starts_on->year;
        $number = $this->sequenceGenerator->next("registration_number:{$year}");

        return sprintf('%02d-%04d', $year % 100, $number);
    }
}

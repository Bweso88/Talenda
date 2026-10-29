<?php

declare(strict_types=1);

namespace App\Modules\Enrollments\Actions;

use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Support\SequenceGenerator;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Enrollments\Documents\SchoolDocumentRenderer;
use App\Modules\Enrollments\Enums\EnrollmentStatus;
use App\Modules\Enrollments\Models\Enrollment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Documents délivrés par le secrétariat : certificat de scolarité
 * (numéroté et journalisé) et cartes scolaires.
 */
final class IssueDocuments
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SchoolDocumentRenderer $renderer,
        private readonly SequenceGenerator $sequenceGenerator,
        private readonly RecordAudit $recordAudit,
    ) {}

    /**
     * @return string contenu du PDF
     */
    public function schoolCertificate(Enrollment $enrollment, ?Model $actor = null): string
    {
        if ($enrollment->status !== EnrollmentStatus::Enrolled) {
            throw new BusinessRuleViolation('Un certificat de scolarité ne peut être délivré que pour un élève inscrit.');
        }

        $enrollment->loadMissing(['student', 'schoolClass.level', 'schoolYear']);
        $tenant = $this->tenant();

        return DB::transaction(function () use ($enrollment, $tenant, $actor): string {
            $number = sprintf(
                '%04d/%s',
                $this->sequenceGenerator->next("school_certificate:{$enrollment->schoolYear->name}"),
                $enrollment->schoolYear->name,
            );

            $this->recordAudit->handle('document.school_certificate_issued', actor: $actor, subject: $enrollment, metadata: [
                'number' => $number,
                'student' => $enrollment->student->registration_number,
            ]);

            return $this->renderer->render($tenant, 'documents.school-certificate', [
                'enrollment' => $enrollment,
                'student' => $enrollment->student,
                'number' => $number,
                'issuedOn' => now($tenant->timezone),
            ]);
        });
    }

    /**
     * Cartes scolaires (8 par page A4, à découper).
     *
     * @param  Collection<int, Enrollment>  $enrollments
     * @return string contenu du PDF
     */
    public function studentCards(Collection $enrollments, ?Model $actor = null): string
    {
        $enrollments = $enrollments
            ->filter(fn (Enrollment $enrollment): bool => $enrollment->status->isActive())
            ->load(['student', 'schoolClass', 'schoolYear'])
            ->sortBy(['schoolClass.name', 'student.last_name', 'student.first_names'])
            ->values();

        if ($enrollments->isEmpty()) {
            throw new BusinessRuleViolation('Aucun élève inscrit dans la sélection.');
        }

        $this->recordAudit->handle('document.student_cards_issued', actor: $actor, metadata: ['count' => $enrollments->count()]);

        return $this->renderer->render($this->tenant(), 'documents.student-cards', [
            'enrollments' => $enrollments,
            'photos' => $enrollments->mapWithKeys(fn (Enrollment $enrollment): array => [
                $enrollment->id => $this->renderer->imageDataUri('local', $enrollment->student->photo_path),
            ]),
        ]);
    }

    private function tenant(): Tenant
    {
        return $this->tenantContext->get() ?? throw new LogicException('Aucune école active.');
    }
}

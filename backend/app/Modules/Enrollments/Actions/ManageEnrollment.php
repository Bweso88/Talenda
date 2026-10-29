<?php

declare(strict_types=1);

namespace App\Modules\Enrollments\Actions;

use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Enrollments\Enums\EnrollmentStatus;
use App\Modules\Enrollments\Events\StudentLeft;
use App\Modules\Enrollments\Models\Enrollment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Vie d'une inscription en cours d'année : confirmation d'une
 * préinscription, changement de classe, départ ou transfert.
 */
final class ManageEnrollment
{
    public function __construct(
        private readonly EnsureSeatAvailable $ensureSeatAvailable,
        private readonly RecordAudit $recordAudit,
    ) {}

    public function confirm(Enrollment $enrollment, ?Model $actor = null): void
    {
        if ($enrollment->status !== EnrollmentStatus::PreRegistered) {
            throw new BusinessRuleViolation('Seule une préinscription peut être confirmée.');
        }

        $enrollment->update(['status' => EnrollmentStatus::Enrolled]);

        $this->recordAudit->handle('enrollment.confirmed', actor: $actor, subject: $enrollment);
    }

    public function changeClass(Enrollment $enrollment, SchoolClass $schoolClass, ?Model $actor = null): void
    {
        $this->ensureActive($enrollment);

        if ($schoolClass->school_year_id !== $enrollment->school_year_id) {
            throw new BusinessRuleViolation('La nouvelle classe doit être de la même année scolaire.');
        }

        if ($schoolClass->is($enrollment->schoolClass)) {
            return;
        }

        $this->ensureSeatAvailable->handle($schoolClass);

        $previousClass = $enrollment->schoolClass->name;
        $enrollment->update(['school_class_id' => $schoolClass->id]);

        $this->recordAudit->handle('enrollment.class_changed', actor: $actor, subject: $enrollment, metadata: [
            'from' => $previousClass,
            'to' => $schoolClass->name,
        ]);
    }

    /**
     * Départ en cours d'année. La ligne est conservée : l'historique et,
     * plus tard, les éventuels arriérés de paiement restent consultables.
     */
    public function recordDeparture(
        Enrollment $enrollment,
        EnrollmentStatus $status,
        Carbon $leftOn,
        ?string $reason = null,
        ?string $destinationSchool = null,
        ?Model $actor = null,
    ): void {
        $this->ensureActive($enrollment);

        if (! in_array($status, [EnrollmentStatus::Withdrawn, EnrollmentStatus::TransferredOut], true)) {
            throw new BusinessRuleViolation('Statut de départ invalide.');
        }

        if ($leftOn->lt($enrollment->enrolled_on)) {
            throw new BusinessRuleViolation('La date de départ précède la date d\'inscription.');
        }

        $enrollment->update([
            'status' => $status,
            'left_on' => $leftOn,
            'leaving_reason' => $reason,
            'destination_school' => $status === EnrollmentStatus::TransferredOut ? $destinationSchool : null,
        ]);

        $this->recordAudit->handle('enrollment.left', actor: $actor, subject: $enrollment, metadata: [
            'status' => $status->value,
            'reason' => $reason,
        ]);

        StudentLeft::dispatch($enrollment);
    }

    private function ensureActive(Enrollment $enrollment): void
    {
        if (! $enrollment->status->isActive()) {
            throw new BusinessRuleViolation('Cette inscription n\'est plus en cours.');
        }

        if ($enrollment->schoolYear->isClosed()) {
            throw new BusinessRuleViolation("L'année {$enrollment->schoolYear->name} est clôturée.");
        }
    }
}

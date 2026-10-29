<?php

declare(strict_types=1);

namespace App\Modules\SchoolLife\Actions;

use App\Models\User;
use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Enrollments\Models\Enrollment;
use App\Modules\SchoolLife\Enums\AttendanceStatus;
use App\Modules\SchoolLife\Enums\JustificationReason;
use App\Modules\SchoolLife\Enums\JustificationStatus;
use App\Modules\SchoolLife\Models\AbsenceJustification;
use App\Modules\SchoolLife\Models\AttendanceRecord;
use Illuminate\Support\Carbon;

/**
 * Justifie les absences d'un élève sur une période (certificat médical,
 * mot des parents). Un justificatif apporté après la publication des
 * bulletins ne modifie pas le bulletin déjà publié.
 */
final class JustifyAbsences
{
    public function __construct(private readonly RecordAudit $recordAudit) {}

    public function handle(Enrollment $enrollment, Carbon $startsOn, Carbon $endsOn, JustificationReason $reason, ?string $comment, User $user): AbsenceJustification
    {
        if ($endsOn->lt($startsOn)) {
            throw new BusinessRuleViolation('La date de fin du justificatif est avant sa date de début.');
        }

        $enrollment->loadMissing('schoolYear');

        if ($startsOn->lt($enrollment->schoolYear->starts_on) || $endsOn->gt($enrollment->schoolYear->ends_on)) {
            throw new BusinessRuleViolation("Le justificatif doit rester dans l'année scolaire {$enrollment->schoolYear->name}.");
        }

        $justification = AbsenceJustification::create([
            'enrollment_id' => $enrollment->id,
            'starts_on' => $startsOn->toDateString(),
            'ends_on' => $endsOn->toDateString(),
            'reason' => $reason,
            'comment' => blank($comment) ? null : trim($comment),
            'status' => JustificationStatus::Accepted,
            'recorded_by' => $user->id,
        ]);

        $this->recordAudit->handle('absence.justified', actor: $user, subject: $justification, metadata: [
            'enrollment' => $enrollment->uuid,
            'from' => $startsOn->toDateString(),
            'to' => $endsOn->toDateString(),
            'absences_covered' => self::coveredAbsences($justification),
        ]);

        return $justification;
    }

    /**
     * Nombre d'absences déjà relevées que le justificatif couvre.
     */
    public static function coveredAbsences(AbsenceJustification $justification): int
    {
        return AttendanceRecord::query()
            ->where('enrollment_id', $justification->enrollment_id)
            ->where('status', AttendanceStatus::Absent)
            ->whereHas('session', fn ($query) => $query->whereBetween('held_on', [$justification->starts_on->toDateString(), $justification->ends_on->toDateString()]))
            ->count();
    }

    public function withdraw(AbsenceJustification $justification, User $user): void
    {
        $justification->delete();

        $this->recordAudit->handle('absence.justification_withdrawn', actor: $user, subject: $justification, metadata: [
            'from' => $justification->starts_on->toDateString(),
            'to' => $justification->ends_on->toDateString(),
        ]);
    }
}

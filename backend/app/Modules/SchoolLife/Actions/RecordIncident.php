<?php

declare(strict_types=1);

namespace App\Modules\SchoolLife\Actions;

use App\Models\User;
use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Enrollments\Models\Enrollment;
use App\Modules\SchoolLife\Enums\Sanction;
use App\Modules\SchoolLife\Models\DisciplinaryIncident;
use Illuminate\Support\Carbon;

/**
 * Enregistre (ou corrige) un incident de discipline et sa sanction.
 */
final class RecordIncident
{
    public function __construct(private readonly RecordAudit $recordAudit) {}

    /**
     * @param  array{enrollment_id: int|string, occurred_on: mixed, description: string, sanction: Sanction|string, suspension_days?: mixed, parents_informed?: bool|null}  $data
     */
    public function handle(array $data, User $user, ?DisciplinaryIncident $incident = null): DisciplinaryIncident
    {
        $sanction = $data['sanction'] instanceof Sanction ? $data['sanction'] : Sanction::from($data['sanction']);
        $days = filled($data['suspension_days'] ?? null) ? (int) $data['suspension_days'] : null;
        $occurredOn = Carbon::parse($data['occurred_on'])->startOfDay();
        $enrollment = Enrollment::query()->with('schoolYear')->findOrFail($data['enrollment_id']);

        if ($sanction === Sanction::Suspension && ($days === null || $days < 1)) {
            throw new BusinessRuleViolation('Indiquez le nombre de jours d\'exclusion.');
        }

        if ($occurredOn->lt($enrollment->schoolYear->starts_on) || $occurredOn->gt($enrollment->schoolYear->ends_on)) {
            throw new BusinessRuleViolation("La date de l'incident est en dehors de l'année scolaire {$enrollment->schoolYear->name}.");
        }

        $incident ??= new DisciplinaryIncident(['reported_by' => $user->id]);
        $isNew = ! $incident->exists;

        $incident->fill([
            'enrollment_id' => $enrollment->id,
            'occurred_on' => $occurredOn->toDateString(),
            'description' => trim($data['description']),
            'sanction' => $sanction,
            'suspension_days' => $sanction === Sanction::Suspension ? $days : null,
            'parents_informed' => (bool) ($data['parents_informed'] ?? false),
        ])->save();

        $this->recordAudit->handle($isNew ? 'discipline.recorded' : 'discipline.updated', actor: $user, subject: $incident, metadata: [
            'enrollment' => $enrollment->uuid,
            'sanction' => $sanction->value,
        ]);

        return $incident;
    }
}

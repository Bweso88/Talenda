<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Actions;

use App\Models\User;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Assessments\Models\AnnualResult;
use App\Modules\Assessments\Models\GradingSetting;
use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Enrollments\Enums\YearEndDecision;
use App\Modules\Enrollments\Models\Enrollment;
use Illuminate\Support\Facades\DB;

/**
 * Décisions de fin d'année (conseil de classe du dernier trimestre). Elles
 * déterminent le niveau proposé à la réinscription (module Inscriptions).
 */
final class YearEndDecisions
{
    public function __construct(private readonly RecordAudit $recordAudit) {}

    /**
     * Proposition selon la moyenne annuelle : admis au-dessus de la moyenne
     * de passage (fin de cycle si le niveau n'a pas de suite dans l'école),
     * redoublement en dessous. Le conseil corrige ensuite au cas par cas.
     */
    public static function propose(Enrollment $enrollment, ?AnnualResult $annualResult, GradingSetting $settings): ?YearEndDecision
    {
        if ($annualResult?->average === null) {
            return null;
        }

        if ((float) $annualResult->average < (float) $settings->passing_average) {
            return YearEndDecision::Repeat;
        }

        return $enrollment->schoolClass->level->next_level_id === null
            ? YearEndDecision::Graduated
            : YearEndDecision::Promoted;
    }

    /**
     * @param  array<int, string|null>  $decisions  par id d'inscription
     */
    public function save(SchoolClass $schoolClass, array $decisions, User $user): void
    {
        if ($schoolClass->schoolYear->isClosed()) {
            throw new BusinessRuleViolation("L'année {$schoolClass->schoolYear->name} est clôturée.");
        }

        DB::transaction(function () use ($schoolClass, $decisions, $user): void {
            $enrollments = $schoolClass->enrollments()->whereIn('id', array_keys($decisions))->get()->keyBy('id');

            foreach ($decisions as $enrollmentId => $decision) {
                $enrollment = $enrollments->get($enrollmentId) ?? throw new BusinessRuleViolation('Un élève n\'appartient pas à cette classe.');

                $enrollment->update(['decision' => blank($decision) ? null : YearEndDecision::from($decision)]);
            }

            $this->recordAudit->handle('enrollment.decisions_recorded', actor: $user, subject: $schoolClass, metadata: ['count' => count($decisions)]);
        });
    }
}

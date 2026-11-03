<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Actions;

use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\Term;
use App\Modules\Assessments\Models\AnnualResult;
use App\Modules\Assessments\Models\Assessment;
use App\Modules\Assessments\Models\GradingSetting;
use App\Modules\Assessments\Models\Score;
use App\Modules\Assessments\Models\TermResult;
use App\Modules\Core\Documents\SchoolDocumentRenderer;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use LogicException;

/**
 * Documents d'une classe pour une période publiée, à côté des bulletins :
 * - relevé de notes : chaque note de chaque évaluation, par élève ;
 * - procès-verbal du conseil de classe : tableau récapitulatif des
 *   moyennes par matière, rangs, mentions, absences (et, en fin d'année,
 *   moyenne annuelle et décision).
 *
 * Ils reprennent les résultats figés à la publication : un document
 * réimprimé plus tard est identique.
 */
final class IssueClassDocuments
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SchoolDocumentRenderer $renderer,
    ) {}

    public function transcripts(Term $term, SchoolClass $schoolClass): string
    {
        $results = $this->results($term, $schoolClass);

        $assessments = Assessment::query()
            ->where('term_id', $term->id)
            ->whereHas('classSubject', fn ($query) => $query->where('school_class_id', $schoolClass->id))
            ->with('assessmentType')
            ->orderBy('held_on')
            ->orderBy('id')
            ->get();

        $scores = Score::query()
            ->whereIn('assessment_id', $assessments->modelKeys())
            ->whereIn('enrollment_id', $results->pluck('enrollment_id'))
            ->get()
            ->groupBy('enrollment_id')
            ->map(fn (Collection $scores) => $scores->keyBy('assessment_id'));

        return $this->renderer->render($this->tenant(), 'documents.transcripts', [
            'term' => $term,
            'results' => $results,
            'assessmentsBySubject' => $assessments->groupBy('class_subject_id'),
            'scores' => $scores,
            'grading' => GradingSetting::current(),
        ]);
    }

    public function councilMinutes(Term $term, SchoolClass $schoolClass): string
    {
        $results = $this->results($term, $schoolClass)->sortBy(fn (TermResult $result): array => [$result->rank ?? PHP_INT_MAX, $result->enrollment->student->last_name])->values();
        $term->loadMissing('schoolYear.terms');
        $isLastTerm = $term->position === $term->schoolYear->terms->max('position');
        $grading = GradingSetting::current();
        $averages = $results->pluck('average')->filter(fn ($average): bool => $average !== null)->map(fn ($average): float => (float) $average);

        return $this->renderer->render($this->tenant(), 'documents.council-minutes', [
            'term' => $term,
            'schoolClass' => $schoolClass->loadMissing(['headTeacher', 'level']),
            'results' => $results,
            // Colonnes : les matières du bulletin, dans son ordre.
            'subjects' => $results->flatMap->subjectResults->sortBy('position')->unique('subject_name')->pluck('subject_name')->values(),
            'grading' => $grading,
            'isLastTerm' => $isLastTerm,
            'annualResults' => $isLastTerm
                ? AnnualResult::query()->whereIn('enrollment_id', $results->pluck('enrollment_id'))->get()->keyBy('enrollment_id')
                : collect(),
            'stats' => [
                'size' => $results->count(),
                'graded' => $averages->count(),
                'passing' => $averages->filter(fn (float $average): bool => $average >= (float) $grading->passing_average)->count(),
                'class_average' => $averages->isEmpty() ? null : round($averages->avg(), 2),
                'highest' => $averages->max(),
                'lowest' => $averages->min(),
            ],
        ], 'landscape');
    }

    /**
     * @return Collection<int, TermResult>
     */
    private function results(Term $term, SchoolClass $schoolClass): Collection
    {
        $results = TermResult::query()
            ->where('term_id', $term->id)
            ->whereHas('enrollment', fn ($query) => $query->where('school_class_id', $schoolClass->id))
            ->with(['subjectResults', 'enrollment.student'])
            ->get()
            ->sortBy(fn (TermResult $result): string => $result->enrollment->student->last_name.' '.$result->enrollment->student->first_names)
            ->values();

        if ($results->isEmpty()) {
            throw new BusinessRuleViolation("Aucun bulletin publié pour la {$schoolClass->name} au {$term->name}.");
        }

        $term->loadMissing('schoolYear');

        return $results;
    }

    private function tenant(): Tenant
    {
        return $this->tenantContext->get() ?? throw new LogicException('Aucune école active.');
    }
}

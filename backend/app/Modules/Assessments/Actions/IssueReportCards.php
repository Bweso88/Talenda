<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Actions;

use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\Term;
use App\Modules\Assessments\Models\AnnualResult;
use App\Modules\Assessments\Models\GradingSetting;
use App\Modules\Assessments\Models\TermResult;
use App\Modules\Core\Documents\SchoolDocumentRenderer;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use LogicException;

/**
 * Bulletins PDF d'une classe pour une période publiée (un bulletin par
 * page), produits à partir des résultats figés uniquement.
 */
final class IssueReportCards
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SchoolDocumentRenderer $renderer,
    ) {}

    /**
     * @param  list<int>|null  $enrollmentIds  restreindre à certains élèves
     */
    public function handle(Term $term, SchoolClass $schoolClass, ?array $enrollmentIds = null): string
    {
        $tenant = $this->tenantContext->get() ?? throw new LogicException('Aucune école active.');

        /** @var Collection<int, TermResult> $results */
        $results = TermResult::query()
            ->where('term_id', $term->id)
            ->whereHas('enrollment', fn ($query) => $query->where('school_class_id', $schoolClass->id))
            ->when($enrollmentIds !== null, fn ($query) => $query->whereIn('enrollment_id', $enrollmentIds))
            ->with(['subjectResults', 'enrollment.student'])
            ->get()
            ->sortBy(fn (TermResult $result): string => $result->enrollment->student->last_name.' '.$result->enrollment->student->first_names)
            ->values();

        if ($results->isEmpty()) {
            throw new BusinessRuleViolation("Aucun bulletin publié pour la {$schoolClass->name} au {$term->name}.");
        }

        $term->loadMissing('schoolYear.terms');
        $isLastTerm = $term->position === $term->schoolYear->terms->max('position');

        return $this->renderer->render($tenant, 'documents.report-cards', [
            'term' => $term,
            'results' => $results,
            'grading' => GradingSetting::current(),
            'isLastTerm' => $isLastTerm,
            'termAverages' => $isLastTerm ? $this->termAverages($results) : collect(),
            'annualResults' => $isLastTerm
                ? AnnualResult::query()->whereIn('enrollment_id', $results->pluck('enrollment_id'))->get()->keyBy('enrollment_id')
                : collect(),
        ]);
    }

    /**
     * Moyennes de chaque période par élève, pour le récapitulatif annuel.
     *
     * @param  Collection<int, TermResult>  $results
     * @return SupportCollection<int, SupportCollection<int, string|null>>
     */
    private function termAverages(Collection $results): SupportCollection
    {
        return TermResult::query()
            ->whereIn('enrollment_id', $results->pluck('enrollment_id'))
            ->with('term')
            ->get()
            ->groupBy('enrollment_id')
            ->map(fn ($termResults) => $termResults->sortBy('term.position')->mapWithKeys(fn (TermResult $result): array => [$result->term->position => $result->average]));
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Actions;

use App\Models\User;
use App\Modules\Academic\Enums\TermStatus;
use App\Modules\Academic\Models\ClassSubject;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\Term;
use App\Modules\Assessments\Models\GradingSetting;
use App\Modules\Assessments\Models\TermComment;
use App\Modules\Assessments\Models\TermResult;
use App\Modules\Assessments\Models\TermSubjectResult;
use App\Modules\Assessments\Support\ClassTermResults;
use App\Modules\Assessments\Support\ComputeClassTermResults;
use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Cycle de vie d'une période (docs/PRODUCT_ARCHITECTURE.md §9) :
 * saisie ouverte → verrouillée (conseil de classe) → publiée (résultats
 * figés, bulletins). Rouvrir une période publiée efface ses résultats
 * figés et laisse une trace.
 */
final class ManageTerm
{
    public function __construct(
        private readonly ComputeClassTermResults $compute,
        private readonly ComputeAnnualResults $computeAnnualResults,
        private readonly RecordAudit $recordAudit,
    ) {}

    public function lock(Term $term, User $user): void
    {
        $this->transition($term, TermStatus::Open, TermStatus::Locked, 'term.locked', $user);
    }

    public function unlock(Term $term, User $user): void
    {
        $this->transition($term, TermStatus::Locked, TermStatus::Open, 'term.unlocked', $user);
    }

    /**
     * Calcule et fige les résultats de toutes les classes de l'année.
     */
    public function publish(Term $term, User $user): int
    {
        if ($term->status !== TermStatus::Locked) {
            throw new BusinessRuleViolation('Verrouillez la saisie avant de publier les bulletins.');
        }

        $term->loadMissing('schoolYear');

        return DB::transaction(function () use ($term, $user): int {
            $count = 0;
            $publishedAt = now();
            $settings = GradingSetting::current();

            $classes = SchoolClass::query()
                ->where('school_year_id', $term->school_year_id)
                ->with('headTeacher')
                ->get();

            foreach ($classes as $schoolClass) {
                $count += $this->freeze($term, $schoolClass, $this->compute->handle($term, $schoolClass), $settings, $publishedAt);
            }

            $term->update(['status' => TermStatus::Published]);
            $this->computeAnnualResults->handle($term->schoolYear);

            $this->recordAudit->handle('term.published', actor: $user, subject: $term, metadata: ['report_cards' => $count]);

            return $count;
        });
    }

    /**
     * Retour à « verrouillée » pour corriger : les résultats figés sont
     * effacés et seront recalculés à la prochaine publication.
     */
    public function reopen(Term $term, User $user, string $reason): void
    {
        if ($term->status !== TermStatus::Published) {
            throw new BusinessRuleViolation('Seule une période publiée peut être rouverte.');
        }

        if (blank($reason)) {
            throw new BusinessRuleViolation('Indiquez pourquoi les bulletins sont rouverts.');
        }

        DB::transaction(function () use ($term, $user, $reason): void {
            $deleted = TermResult::query()->where('term_id', $term->id)->count();
            TermResult::query()->where('term_id', $term->id)->delete();
            $term->update(['status' => TermStatus::Locked]);
            $this->computeAnnualResults->handle($term->schoolYear);

            $this->recordAudit->handle('term.reopened', actor: $user, subject: $term, metadata: [
                'reason' => $reason,
                'report_cards_withdrawn' => $deleted,
            ]);
        });
    }

    private function freeze(Term $term, SchoolClass $schoolClass, ClassTermResults $results, GradingSetting $settings, DateTimeInterface $publishedAt): int
    {
        $comments = TermComment::query()
            ->where('term_id', $term->id)
            ->whereIn('enrollment_id', array_keys($results->enrollments))
            ->get()
            ->groupBy('enrollment_id');

        foreach ($results->enrollments as $enrollmentId => $enrollment) {
            $general = $results->generals[$enrollmentId];
            $rank = $results->rank($enrollmentId);
            $studentComments = $comments->get($enrollmentId, collect());

            $termResult = TermResult::create([
                'term_id' => $term->id,
                'enrollment_id' => $enrollmentId,
                'class_name' => $schoolClass->name,
                'average' => $results->average($enrollmentId),
                'rank' => $rank['rank'] ?? null,
                'rank_tied' => $rank['tied'] ?? false,
                'class_size' => $results->classSize(),
                'class_average' => $results->classStats['average'],
                'class_highest' => $results->classStats['highest'],
                'class_lowest' => $results->classStats['lowest'],
                'total_coefficient' => $general['coefficient'],
                'total_points' => $general['average'] === null ? null : $general['points'],
                'distinction' => $settings->distinctionFor($results->average($enrollmentId)),
                'council_comment' => $studentComments->firstWhere('class_subject_id', null)?->body,
                'head_teacher_name' => $schoolClass->headTeacher?->full_name,
                'published_at' => $publishedAt,
            ]);

            $position = 0;

            foreach ($results->classSubjects as $classSubjectId => $classSubject) {
                /** @var ClassSubject $classSubject */
                $stats = $results->subjectStats[$classSubjectId];

                TermSubjectResult::create([
                    'term_result_id' => $termResult->id,
                    'class_subject_id' => $classSubjectId,
                    'position' => ++$position,
                    'subject_name' => $classSubject->subject->name,
                    'teacher_name' => $classSubject->teacher?->full_name,
                    'coefficient' => $classSubject->coefficient,
                    'average' => $results->subjectAverage($enrollmentId, $classSubjectId),
                    'rank' => $results->subjectRanks[$classSubjectId][$enrollmentId]['rank'] ?? null,
                    'class_average' => $stats['average'],
                    'class_highest' => $stats['highest'],
                    'class_lowest' => $stats['lowest'],
                    'comment' => $studentComments->firstWhere('class_subject_id', $classSubjectId)?->body,
                ]);
            }
        }

        return count($results->enrollments);
    }

    private function transition(Term $term, TermStatus $from, TermStatus $to, string $auditAction, User $user): void
    {
        if ($term->status !== $from) {
            throw new BusinessRuleViolation("« {$term->name} » n'est pas dans l'état attendu ({$from->getLabel()}).");
        }

        $term->update(['status' => $to]);

        $this->recordAudit->handle($auditAction, actor: $user, subject: $term);
    }
}

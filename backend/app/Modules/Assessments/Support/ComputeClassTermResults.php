<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Support;

use App\Modules\Academic\Models\ClassSubject;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Academic\Models\Term;
use App\Modules\Assessments\Enums\AbsencePolicy;
use App\Modules\Assessments\Enums\ScoreStatus;
use App\Modules\Assessments\Models\Assessment;
use App\Modules\Assessments\Models\AssessmentType;
use App\Modules\Assessments\Models\GradingSetting;
use App\Modules\Assessments\Models\Score;
use App\Modules\Enrollments\Enums\EnrollmentStatus;
use App\Modules\Enrollments\Models\Enrollment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Charge les notes d'une classe pour une période et calcule moyennes,
 * rangs et statistiques avec GradeCalculator.
 */
final class ComputeClassTermResults
{
    public function handle(Term $term, SchoolClass $schoolClass): ClassTermResults
    {
        $settings = GradingSetting::current();
        $enrollments = self::enrollmentsOf($term, $schoolClass);

        /** @var Collection<int, ClassSubject> $classSubjects */
        $classSubjects = $schoolClass->classSubjects()
            ->with(['subject', 'teacher'])
            ->get()
            ->sortBy([['coefficient', 'desc'], fn (ClassSubject $a, ClassSubject $b): int => strcmp($a->subject->name, $b->subject->name)])
            ->values();

        $weights = AssessmentType::query()->pluck('weight', 'id')->map(fn ($weight): float => (float) $weight)->all();

        $assessments = Assessment::query()
            ->where('term_id', $term->id)
            ->whereIn('class_subject_id', $classSubjects->modelKeys())
            ->with('scores')
            ->get()
            ->groupBy('class_subject_id');

        $subjectAverages = [];
        $subjectRanks = [];
        $subjectStats = [];

        foreach ($classSubjects as $classSubject) {
            $perStudent = [];

            foreach ($enrollments as $enrollment) {
                $byType = [];

                foreach ($assessments->get($classSubject->id, collect()) as $assessment) {
                    /** @var Assessment $assessment */
                    $score = $assessment->scores->firstWhere('enrollment_id', $enrollment->id);
                    $normalized = self::normalized($score, $assessment, $settings);

                    if ($normalized !== null) {
                        $byType[$assessment->assessment_type_id][] = $normalized;
                    }
                }

                $average = GradeCalculator::subjectAverage($byType, $weights);
                $perStudent[$enrollment->id] = $average;
                $subjectAverages[$enrollment->id][$classSubject->id] = $average;
            }

            $subjectRanks[$classSubject->id] = GradeCalculator::ranks($perStudent);
            $subjectStats[$classSubject->id] = GradeCalculator::stats(array_values(array_map(
                fn (float $value): float => GradeCalculator::round($value),
                array_filter($perStudent, fn (?float $value): bool => $value !== null),
            )));
        }

        $generals = [];

        foreach ($enrollments as $enrollment) {
            $generals[$enrollment->id] = GradeCalculator::generalAverage($classSubjects->map(fn (ClassSubject $classSubject): array => [
                'average' => $subjectAverages[$enrollment->id][$classSubject->id] ?? null,
                'coefficient' => (float) $classSubject->coefficient,
            ])->all());
        }

        $averages = array_map(fn (array $general): ?float => $general['average'], $generals);

        return new ClassTermResults(
            enrollments: $enrollments->keyBy('id')->all(),
            classSubjects: $classSubjects->keyBy('id')->all(),
            subjectAverages: $subjectAverages,
            subjectRanks: $subjectRanks,
            subjectStats: $subjectStats,
            generals: $generals,
            ranks: GradeCalculator::ranks($averages),
            classStats: GradeCalculator::stats(array_values(array_map(
                fn (float $value): float => GradeCalculator::round($value),
                array_filter($averages, fn (?float $value): bool => $value !== null),
            ))),
        );
    }

    /**
     * Élèves de la classe pour la période : inscrits (ou année terminée),
     * et ceux partis après la fin de la période. Un élève parti en cours de
     * période n'est pas classé.
     *
     * @return Collection<int, Enrollment>
     */
    public static function enrollmentsOf(Term $term, SchoolClass $schoolClass): Collection
    {
        return $schoolClass->enrollments()
            ->with('student')
            ->where(fn (Builder $query) => $query
                ->whereIn('status', [EnrollmentStatus::Enrolled, EnrollmentStatus::Completed])
                ->orWhere(fn (Builder $left) => $left
                    ->whereIn('status', [EnrollmentStatus::Withdrawn, EnrollmentStatus::TransferredOut])
                    ->where('left_on', '>', $term->ends_on)))
            ->get()
            ->sortBy(fn (Enrollment $enrollment): string => $enrollment->student->last_name.' '.$enrollment->student->first_names)
            ->values();
    }

    /**
     * Note ramenée sur le barème de l'école (20), ou null si elle ne compte
     * pas (pas de note, dispense, absence non comptée).
     */
    private static function normalized(?Score $score, Assessment $assessment, GradingSetting $settings): ?float
    {
        if ($score === null || $score->status === ScoreStatus::Exempted) {
            return null;
        }

        if ($score->status === ScoreStatus::Absent) {
            return $settings->absence_policy === AbsencePolicy::Zero ? 0.0 : null;
        }

        return (float) $score->value / (float) $assessment->max_score * $settings->max_score;
    }
}

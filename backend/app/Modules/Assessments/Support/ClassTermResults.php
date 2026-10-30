<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Support;

use App\Modules\Academic\Models\ClassSubject;
use App\Modules\Enrollments\Models\Enrollment;

/**
 * Résultats calculés d'une classe pour une période (avant figeage) : ce que
 * le conseil de classe examine et ce que la publication enregistre.
 */
final class ClassTermResults
{
    /**
     * @param  array<int, Enrollment>  $enrollments  par id d'inscription, ordre alphabétique
     * @param  array<int, ClassSubject>  $classSubjects  par id, ordre d'affichage
     * @param  array<int, array<int, float|null>>  $subjectAverages  [inscription][matière]
     * @param  array<int, array<int, array{rank: int, tied: bool}>>  $subjectRanks  [matière][inscription]
     * @param  array<int, array{average: float|null, highest: float|null, lowest: float|null}>  $subjectStats  [matière]
     * @param  array<int, array{average: float|null, points: float, coefficient: float}>  $generals  [inscription]
     * @param  array<int, array{rank: int, tied: bool}>  $ranks  [inscription]
     * @param  array{average: float|null, highest: float|null, lowest: float|null}  $classStats
     */
    public function __construct(
        public readonly array $enrollments,
        public readonly array $classSubjects,
        public readonly array $subjectAverages,
        public readonly array $subjectRanks,
        public readonly array $subjectStats,
        public readonly array $generals,
        public readonly array $ranks,
        public readonly array $classStats,
    ) {}

    public function average(int $enrollmentId): ?float
    {
        $average = $this->generals[$enrollmentId]['average'] ?? null;

        return $average === null ? null : GradeCalculator::round($average);
    }

    public function subjectAverage(int $enrollmentId, int $classSubjectId): ?float
    {
        $average = $this->subjectAverages[$enrollmentId][$classSubjectId] ?? null;

        return $average === null ? null : GradeCalculator::round($average);
    }

    /**
     * @return array{rank: int, tied: bool}|null
     */
    public function rank(int $enrollmentId): ?array
    {
        return $this->ranks[$enrollmentId] ?? null;
    }

    public function classSize(): int
    {
        return count($this->enrollments);
    }
}

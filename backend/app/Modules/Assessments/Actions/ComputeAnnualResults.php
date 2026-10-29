<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Actions;

use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Assessments\Models\AnnualResult;
use App\Modules\Assessments\Models\TermResult;
use App\Modules\Assessments\Support\GradeCalculator;
use Illuminate\Support\Facades\DB;

/**
 * Moyennes et rangs annuels : moyenne des moyennes des périodes publiées,
 * classement par classe. Recalculé à chaque publication ou réouverture.
 */
final class ComputeAnnualResults
{
    public function handle(SchoolYear $schoolYear): void
    {
        DB::transaction(function () use ($schoolYear): void {
            $results = TermResult::query()
                ->whereHas('term', fn ($query) => $query->where('school_year_id', $schoolYear->id))
                ->with('enrollment')
                ->get();

            AnnualResult::query()
                ->whereHas('enrollment', fn ($query) => $query->where('school_year_id', $schoolYear->id))
                ->delete();

            foreach ($results->groupBy(fn (TermResult $result): int => $result->enrollment->school_class_id) as $classResults) {
                $averages = [];

                foreach ($classResults->groupBy('enrollment_id') as $enrollmentId => $termResults) {
                    $termAverages = $termResults->pluck('average')->filter(fn ($average): bool => $average !== null)->map(fn ($average): float => (float) $average);
                    $averages[(int) $enrollmentId] = $termAverages->isEmpty() ? null : $termAverages->avg();
                }

                $ranks = GradeCalculator::ranks($averages);

                foreach ($averages as $enrollmentId => $average) {
                    AnnualResult::create([
                        'enrollment_id' => $enrollmentId,
                        'average' => $average === null ? null : GradeCalculator::round($average),
                        'rank' => $ranks[$enrollmentId]['rank'] ?? null,
                        'rank_tied' => $ranks[$enrollmentId]['tied'] ?? false,
                        'class_size' => count($averages),
                    ]);
                }
            }
        });
    }
}

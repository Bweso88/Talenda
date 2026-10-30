<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Actions;

use App\Modules\Assessments\Models\AssessmentType;
use App\Modules\Assessments\Models\GradingSetting;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Tenancy\TenantContext;

/**
 * Règles de notation par défaut d'une nouvelle école (gabarit du pays).
 */
final class SetUpGrading
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function handle(Tenant $tenant): void
    {
        /** @var array{max_score: int, passing_average: int|float, absence_policy: string, assessment_types: list<array{code: string, name: string, weight: int|float}>, distinctions: list<array{name: string, min: int|float}>}|null $preset */
        $preset = config("talenda.academic_presets.{$tenant->country_code}.grading");

        $this->tenantContext->run($tenant, function () use ($preset): void {
            GradingSetting::query()->firstOrCreate([], [
                'max_score' => $preset['max_score'] ?? 20,
                'passing_average' => $preset['passing_average'] ?? 10,
                'absence_policy' => $preset['absence_policy'] ?? 'ignore',
                'distinctions' => $preset['distinctions'] ?? [],
            ]);

            foreach ($preset['assessment_types'] ?? [['code' => 'DEVOIR', 'name' => 'Devoir', 'weight' => 1]] as $position => $type) {
                AssessmentType::query()->firstOrCreate(['code' => $type['code']], [
                    'name' => $type['name'],
                    'weight' => $type['weight'],
                    'position' => $position + 1,
                ]);
            }
        });
    }
}

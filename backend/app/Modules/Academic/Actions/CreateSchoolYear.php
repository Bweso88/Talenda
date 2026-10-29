<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Modules\Academic\Events\SchoolYearCreated;
use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Academic\Models\Term;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Crée une année scolaire « en préparation » et ses périodes, à partir
 * du calendrier du gabarit de pays (ex. Congo : octobre → juillet,
 * 3 trimestres). Les dates restent modifiables ensuite.
 */
final class CreateSchoolYear
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    /**
     * @param  int  $startYear  année civile de la rentrée (2026 pour 2026-2027)
     */
    public function handle(int $startYear): SchoolYear
    {
        $tenant = $this->tenantContext->get() ?? throw new LogicException('Aucune école active.');
        $preset = $this->calendarPreset($tenant);
        $name = $startYear.'-'.($startYear + 1);

        if (SchoolYear::query()->where('name', $name)->exists()) {
            throw new BusinessRuleViolation("L'année scolaire {$name} existe déjà.");
        }

        $startMonth = $preset['school_year']['start_month'];
        $endMonth = $preset['school_year']['end_month'];

        return DB::transaction(function () use ($name, $startYear, $startMonth, $endMonth, $preset): SchoolYear {
            $schoolYear = SchoolYear::create([
                'name' => $name,
                'starts_on' => Carbon::create($startYear, $startMonth, 1),
                'ends_on' => Carbon::create($startYear + 1, $endMonth, 1)->endOfMonth(),
            ]);

            foreach ($preset['terms'] as $index => $term) {
                Term::create([
                    'school_year_id' => $schoolYear->id,
                    'name' => $term['name'],
                    'position' => $index + 1,
                    'starts_on' => $this->dateInSchoolYear($term['start'], $startYear, $startMonth),
                    'ends_on' => $this->dateInSchoolYear($term['end'], $startYear, $startMonth),
                ]);
            }

            SchoolYearCreated::dispatch($schoolYear);

            return $schoolYear;
        });
    }

    /**
     * « 01-05 » → 5 janvier de l'année civile qui convient : avant le mois
     * de rentrée, la date tombe l'année suivante.
     */
    private function dateInSchoolYear(string $monthDay, int $startYear, int $startMonth): Carbon
    {
        [$month, $day] = array_map(intval(...), explode('-', $monthDay));

        return Carbon::create($month >= $startMonth ? $startYear : $startYear + 1, $month, $day);
    }

    /**
     * @return array{school_year: array{start_month: int, end_month: int}, terms: list<array{name: string, start: string, end: string}>}
     */
    private function calendarPreset(Tenant $tenant): array
    {
        /** @var array{school_year: array{start_month: int, end_month: int}, terms: list<array{name: string, start: string, end: string}>}|null $preset */
        $preset = config("talenda.academic_presets.{$tenant->country_code}");

        return $preset ?? [
            'school_year' => ['start_month' => 9, 'end_month' => 6],
            'terms' => [
                ['name' => 'Premier trimestre', 'start' => '09-01', 'end' => '12-20'],
                ['name' => 'Deuxième trimestre', 'start' => '01-05', 'end' => '03-31'],
                ['name' => 'Troisième trimestre', 'start' => '04-10', 'end' => '06-30'],
            ],
        ];
    }
}

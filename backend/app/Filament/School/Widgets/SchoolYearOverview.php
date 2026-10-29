<?php

namespace App\Filament\School\Widgets;

use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Academic\Models\StaffMember;
use App\Modules\Academic\Models\Subject;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Vue d'ensemble de l'année en cours sur le tableau de bord de l'école.
 */
class SchoolYearOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -1;

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $openYear = SchoolYear::query()->open()->withCount('schoolClasses')->first();

        return [
            Stat::make('Année scolaire', $openYear->name ?? 'Aucune année ouverte')
                ->description($openYear === null ? 'Ouvrez une année dans « Années scolaires »' : 'Année ouverte')
                ->color($openYear === null ? 'danger' : 'success'),
            Stat::make('Classes', (string) ($openYear->school_classes_count ?? 0)),
            Stat::make('Personnel en poste', (string) StaffMember::query()->active()->count()),
            Stat::make('Matières', (string) Subject::query()->count()),
        ];
    }
}

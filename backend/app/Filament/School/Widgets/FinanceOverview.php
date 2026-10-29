<?php

namespace App\Filament\School\Widgets;

use App\Modules\Academic\Models\SchoolYear;
use App\Modules\Finances\Models\Payment;
use App\Modules\Finances\Support\Money;
use App\Modules\Finances\Support\UnpaidReport;
use App\Modules\Finances\Support\UnpaidRow;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Recettes et impayés sur le tableau de bord (direction, comptabilité).
 */
class FinanceOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    public static function canView(): bool
    {
        return Filament::auth()->user()?->can('finance.manage') ?? false;
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $collected = fn ($from) => (int) Payment::query()->valid()->where('paid_on', '>=', $from)->sum('amount');
        $openYear = SchoolYear::query()->open()->first();
        $unpaid = $openYear === null ? collect() : app(UnpaidReport::class)->rows($openYear);

        return [
            Stat::make('Encaissé aujourd\'hui', Money::format($collected(today()))),
            Stat::make('Encaissé ce mois-ci', Money::format($collected(today()->startOfMonth()))),
            Stat::make('Impayés en retard', Money::format((int) $unpaid->sum(fn (UnpaidRow $row): int => $row->overdue)))
                ->description($unpaid->count().' élève(s) concerné(s)')
                ->color($unpaid->isEmpty() ? 'success' : 'danger'),
        ];
    }
}

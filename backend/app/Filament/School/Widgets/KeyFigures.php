<?php

namespace App\Filament\School\Widgets;

use App\Filament\School\Support\DashboardFigures;
use App\Models\User;
use App\Modules\Finances\Support\Money;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Indicateurs clés, filtrés selon les droits de la personne connectée.
 */
class KeyFigures extends StatsOverviewWidget
{
    protected static ?int $sort = -5;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return ($user?->can('student.view') ?? false) || ($user?->can('finance.view') ?? false) || ($user?->can('attendance.manage') ?? false);
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        /** @var User $user */
        $user = Filament::auth()->user();
        $figures = app(DashboardFigures::class);
        $stats = [];

        if ($user->can('student.view')) {
            $stats[] = Stat::make('Élèves inscrits', number_format($figures->enrolled(), 0, ',', ' '))
                ->description($figures->preRegistered() > 0 ? "{$figures->preRegistered()} préinscription(s) à confirmer" : 'Année '.($figures->year()->name ?? '—'))
                ->descriptionIcon(Heroicon::OutlinedUserGroup)
                ->color('primary');
        }

        if ($user->can('attendance.manage')) {
            $today = $figures->attendanceToday();
            $present = $today['called'] - $today['absent'];
            $rate = $today['called'] === 0 ? null : round($present * 100 / $today['called'], 1);

            $stats[] = Stat::make('Présents aujourd\'hui', $today['called'] === 0 ? '—' : (string) $present)
                ->description($rate === null ? 'Aucun appel fait aujourd\'hui' : "{$rate} % • {$today['absent']} absent(s), {$today['late']} retard(s)")
                ->descriptionIcon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color($rate === null ? 'gray' : ($rate >= 90 ? 'success' : 'warning'));
        }

        if ($user->can('finance.view')) {
            $finance = $figures->finance();
            $overdue = $figures->overdue();
            $rate = $finance['billed'] === 0 ? 0 : round($finance['collected'] * 100 / $finance['billed'], 1);

            $stats[] = Stat::make('Encaissé sur l\'année', Money::format($finance['collected']))
                ->description("{$rate} % du facturé • ".Money::format($finance['month']).' ce mois-ci')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->chart(array_map(fn (int $amount): float => (float) $amount, array_values($figures->monthlyCollections())))
                ->color('success');

            $stats[] = Stat::make('En retard de paiement', Money::format($overdue['amount']))
                ->description($overdue['amount'] === 0 ? 'Aucun retard' : "{$overdue['families']} famille(s) à relancer")
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($overdue['amount'] === 0 ? 'success' : 'danger');
        }

        return $stats;
    }
}

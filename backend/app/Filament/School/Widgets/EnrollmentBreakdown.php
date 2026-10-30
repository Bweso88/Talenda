<?php

namespace App\Filament\School\Widgets;

use App\Filament\School\Support\DashboardFigures;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * Répartition des élèves inscrits par cycle.
 */
class EnrollmentBreakdown extends Widget
{
    protected static ?int $sort = 5;

    protected string $view = 'filament.school.widgets.enrollment-breakdown';

    /**
     * @var int|string|array<string, int>
     */
    protected int|string|array $columnSpan = ['md' => 2, 'xl' => 1];

    private const COLORS = ['#0f2c5c', '#1e5aa8', '#1a9aa0', '#f0a030', '#8aa6cc'];

    public static function canView(): bool
    {
        return Filament::auth()->user()?->can('student.view') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $figures = app(DashboardFigures::class);
        $total = max(1, $figures->enrolled());

        return [
            'total' => $figures->enrolled(),
            'cycles' => array_map(fn (array $cycle, int $index): array => [
                ...$cycle,
                'percent' => round($cycle['count'] * 100 / $total),
                'color' => self::COLORS[$index % count(self::COLORS)],
            ], $figures->byCycle(), array_keys($figures->byCycle())),
        ];
    }
}

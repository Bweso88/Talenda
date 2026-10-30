<?php

namespace App\Filament\School\Widgets;

use App\Filament\School\Support\DashboardFigures;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

/**
 * Encaissements mois par mois depuis la rentrée.
 */
class CollectionsChart extends ChartWidget
{
    protected static ?int $sort = 0;

    protected ?string $heading = 'Encaissements par mois (FCFA)';

    protected ?string $maxHeight = '260px';

    /**
     * @var int|string|array<string, int>
     */
    protected int|string|array $columnSpan = ['md' => 2, 'xl' => 2];

    public static function canView(): bool
    {
        return Filament::auth()->user()?->can('finance.view') ?? false;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $months = app(DashboardFigures::class)->monthlyCollections();

        return [
            'datasets' => [[
                'label' => 'Encaissé',
                'data' => array_values($months),
                'backgroundColor' => '#1e5aa8',
                'borderRadius' => 6,
            ]],
            'labels' => array_keys($months),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true]],
        ];
    }
}

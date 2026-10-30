<?php

namespace App\Filament\School\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

/**
 * Tableau de bord de l'école : chaque widget ne s'affiche que pour les
 * rôles concernés (direction, caisse, vie scolaire, enseignant).
 */
class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Tableau de bord';

    /**
     * @return int|array<string, int>
     */
    public function getColumns(): int|array
    {
        return ['md' => 2, 'xl' => 3];
    }
}

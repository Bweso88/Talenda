<?php

declare(strict_types=1);

namespace App\Modules\SchoolLife\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Statut d'un élève à l'appel. « Présent » n'est jamais enregistré : c'est
 * l'absence de ligne.
 */
enum AttendanceStatus: string implements HasColor, HasLabel
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';

    public function getLabel(): string
    {
        return match ($this) {
            self::Present => 'Présent',
            self::Absent => 'Absent',
            self::Late => 'Retard',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Present => 'success',
            self::Absent => 'danger',
            self::Late => 'warning',
        };
    }
}

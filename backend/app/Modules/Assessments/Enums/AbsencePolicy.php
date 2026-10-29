<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Prise en compte d'une absence à une évaluation (paramètre de l'école).
 */
enum AbsencePolicy: string implements HasLabel
{
    case Ignore = 'ignore';
    case Zero = 'zero';

    public function getLabel(): string
    {
        return match ($this) {
            self::Ignore => 'Ne pas compter l\'évaluation',
            self::Zero => 'Compter 0',
        };
    }
}

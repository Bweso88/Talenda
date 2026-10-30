<?php

declare(strict_types=1);

namespace App\Modules\Enrollments\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Décision de fin d'année (prise en conseil de classe, module Évaluations).
 * Elle détermine le niveau proposé à la réinscription.
 */
enum YearEndDecision: string implements HasLabel
{
    case Promoted = 'promoted';
    case Repeat = 'repeat';
    case Excluded = 'excluded';
    case Graduated = 'graduated';

    public function getLabel(): string
    {
        return match ($this) {
            self::Promoted => 'Admis(e) en classe supérieure',
            self::Repeat => 'Redouble',
            self::Excluded => 'Exclu(e)',
            self::Graduated => 'Fin de cycle dans l\'école',
        };
    }
}

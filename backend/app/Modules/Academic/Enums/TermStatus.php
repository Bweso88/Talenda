<?php

declare(strict_types=1);

namespace App\Modules\Academic\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Cycle de vie d'une période (docs/PRODUCT_ARCHITECTURE.md §9) :
 * saisie des notes ouverte → verrouillée → bulletins publiés.
 * Les transitions seront gérées par le module Évaluations (étape 4).
 */
enum TermStatus: string implements HasColor, HasLabel
{
    case Open = 'open';
    case Locked = 'locked';
    case Published = 'published';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Saisie ouverte',
            self::Locked => 'Verrouillée',
            self::Published => 'Publiée',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'success',
            self::Locked => 'warning',
            self::Published => 'info',
        };
    }
}

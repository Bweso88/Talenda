<?php

declare(strict_types=1);

namespace App\Modules\SchoolLife\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Échelle des sanctions, de la plus légère à la plus lourde.
 */
enum Sanction: string implements HasColor, HasLabel
{
    case None = 'none';
    case Warning = 'warning';
    case Detention = 'detention';
    case Reprimand = 'reprimand';
    case Suspension = 'suspension';
    case Expulsion = 'expulsion';

    public function getLabel(): string
    {
        return match ($this) {
            self::None => 'Rappel à l\'ordre',
            self::Warning => 'Avertissement',
            self::Detention => 'Retenue',
            self::Reprimand => 'Blâme',
            self::Suspension => 'Exclusion temporaire',
            self::Expulsion => 'Exclusion définitive',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::None, self::Warning => 'gray',
            self::Detention, self::Reprimand => 'warning',
            self::Suspension, self::Expulsion => 'danger',
        };
    }
}

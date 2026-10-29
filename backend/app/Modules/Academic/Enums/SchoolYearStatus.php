<?php

declare(strict_types=1);

namespace App\Modules\Academic\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Cycle de vie d'une année scolaire (docs/PRODUCT_ARCHITECTURE.md §9) :
 * en préparation → ouverte → clôturée. Une seule année ouverte par école.
 */
enum SchoolYearStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Open = 'open';
    case Closed = 'closed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'En préparation',
            self::Open => 'Ouverte',
            self::Closed => 'Clôturée',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Open => 'success',
            self::Closed => 'danger',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Finances\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: string implements HasColor, HasLabel
{
    case Valid = 'valid';
    case Reversed = 'reversed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Valid => 'Valide',
            self::Reversed => 'Annulé',
        };
    }

    public function getColor(): string
    {
        return $this === self::Valid ? 'success' : 'danger';
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Finances\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InvoiceLineKind: string implements HasColor, HasLabel
{
    case Fee = 'fee';
    case Discount = 'discount';
    case Adjustment = 'adjustment';

    public function getLabel(): string
    {
        return match ($this) {
            self::Fee => 'Frais',
            self::Discount => 'Réduction',
            self::Adjustment => 'Correction',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Fee => 'gray',
            self::Discount => 'success',
            self::Adjustment => 'warning',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\SchoolLife\Enums;

use Filament\Support\Contracts\HasLabel;

enum JustificationReason: string implements HasLabel
{
    case Illness = 'illness';
    case Family = 'family';
    case Transport = 'transport';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Illness => 'Maladie',
            self::Family => 'Raison familiale',
            self::Transport => 'Problème de transport',
            self::Other => 'Autre motif',
        };
    }
}

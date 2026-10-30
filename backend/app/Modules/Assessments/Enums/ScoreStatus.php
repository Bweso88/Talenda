<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Enums;

use Filament\Support\Contracts\HasLabel;

enum ScoreStatus: string implements HasLabel
{
    case Present = 'present';
    case Absent = 'absent';
    // Dispensé : ne compte jamais dans la moyenne.
    case Exempted = 'exempted';

    public function getLabel(): string
    {
        return match ($this) {
            self::Present => 'Noté',
            self::Absent => 'Absent',
            self::Exempted => 'Dispensé',
        };
    }
}

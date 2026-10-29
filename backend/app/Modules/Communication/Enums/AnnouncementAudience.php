<?php

declare(strict_types=1);

namespace App\Modules\Communication\Enums;

use Filament\Support\Contracts\HasLabel;

enum AnnouncementAudience: string implements HasLabel
{
    case School = 'school';
    case Cycle = 'cycle';
    case Level = 'level';
    case SchoolClass = 'class';

    public function getLabel(): string
    {
        return match ($this) {
            self::School => 'Toute l\'école',
            self::Cycle => 'Un cycle',
            self::Level => 'Un niveau',
            self::SchoolClass => 'Une classe',
        };
    }
}

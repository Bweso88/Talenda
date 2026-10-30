<?php

declare(strict_types=1);

namespace App\Modules\Enrollments\Enums;

use Filament\Support\Contracts\HasLabel;

enum GuardianRelationship: string implements HasLabel
{
    case Father = 'father';
    case Mother = 'mother';
    case Guardian = 'guardian';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Father => 'Père',
            self::Mother => 'Mère',
            self::Guardian => 'Tuteur / tutrice',
            self::Other => 'Autre',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Enrollments\Enums;

use Filament\Support\Contracts\HasLabel;

enum Gender: string implements HasLabel
{
    case Female = 'F';
    case Male = 'M';

    public function getLabel(): string
    {
        return match ($this) {
            self::Female => 'Fille',
            self::Male => 'Garçon',
        };
    }

    /**
     * Accord des documents officiels : « né » / « née ».
     */
    public function bornLabel(): string
    {
        return $this === self::Female ? 'née' : 'né';
    }

    public function enrolledLabel(): string
    {
        return $this === self::Female ? 'inscrite' : 'inscrit';
    }
}

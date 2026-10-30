<?php

declare(strict_types=1);

namespace App\Modules\Enrollments\Enums;

use Filament\Support\Contracts\HasLabel;

enum EnrollmentType: string implements HasLabel
{
    case New = 'new';
    case ReEnrollment = 're_enrollment';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'Nouvelle inscription',
            self::ReEnrollment => 'Réinscription',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Finances\Enums;

use App\Modules\Enrollments\Enums\EnrollmentType;
use Filament\Support\Contracts\HasLabel;

enum FeeAppliesTo: string implements HasLabel
{
    case All = 'all';
    case New = 'new';
    case ReEnrollment = 're_enrollment';

    public function getLabel(): string
    {
        return match ($this) {
            self::All => 'Tous les élèves',
            self::New => 'Nouveaux élèves',
            self::ReEnrollment => 'Élèves réinscrits',
        };
    }

    public function matches(EnrollmentType $type): bool
    {
        return match ($this) {
            self::All => true,
            self::New => $type === EnrollmentType::New,
            self::ReEnrollment => $type === EnrollmentType::ReEnrollment,
        };
    }
}

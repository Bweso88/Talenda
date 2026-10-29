<?php

declare(strict_types=1);

namespace App\Modules\Enrollments\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EnrollmentStatus: string implements HasColor, HasLabel
{
    // Dossier ouvert, place réservée, inscription pas encore confirmée.
    case PreRegistered = 'pre_registered';
    case Enrolled = 'enrolled';
    case TransferredOut = 'transferred_out';
    case Withdrawn = 'withdrawn';
    // Année terminée normalement.
    case Completed = 'completed';

    public function getLabel(): string
    {
        return match ($this) {
            self::PreRegistered => 'Préinscrit',
            self::Enrolled => 'Inscrit',
            self::TransferredOut => 'Transféré',
            self::Withdrawn => 'Parti',
            self::Completed => 'Année terminée',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PreRegistered => 'warning',
            self::Enrolled => 'success',
            self::TransferredOut, self::Withdrawn => 'danger',
            self::Completed => 'gray',
        };
    }

    /**
     * Occupe une place dans la classe.
     */
    public function isActive(): bool
    {
        return in_array($this, self::active(), true);
    }

    /**
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::PreRegistered, self::Enrolled];
    }
}

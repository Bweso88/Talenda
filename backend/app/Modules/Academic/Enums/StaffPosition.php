<?php

declare(strict_types=1);

namespace App\Modules\Academic\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Fonction d'un membre du personnel, et rôle attribué par défaut quand on
 * lui ouvre un accès (voir GrantStaffAccess).
 */
enum StaffPosition: string implements HasLabel
{
    case Teacher = 'teacher';
    case Director = 'director';
    case AcademicDirector = 'academic_director';
    case Secretary = 'secretary';
    case Accountant = 'accountant';
    case Cashier = 'cashier';
    case Supervisor = 'supervisor';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Teacher => 'Enseignant(e)',
            self::Director => 'Direction',
            self::AcademicDirector => 'Directeur(trice) des études / censeur',
            self::Secretary => 'Secrétariat',
            self::Accountant => 'Comptabilité',
            self::Cashier => 'Caisse',
            self::Supervisor => 'Surveillance',
            self::Other => 'Autre',
        };
    }

    /**
     * Rôle de config/talenda.php attribué par défaut, ou null si la
     * fonction n'ouvre pas d'accès par elle-même.
     */
    public function defaultRole(): ?string
    {
        return $this === self::Other ? null : $this->value;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Core\Enums;

enum MembershipStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Actif',
            self::Suspended => 'Suspendu',
        };
    }
}

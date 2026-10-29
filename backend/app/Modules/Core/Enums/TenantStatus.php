<?php

declare(strict_types=1);

namespace App\Modules\Core\Enums;

enum TenantStatus: string
{
    case Active = 'active';
    // Abonnement impayé : consultation seule (docs/PRODUCT_ARCHITECTURE.md §15).
    case ReadOnly = 'read_only';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::ReadOnly => 'Lecture seule',
            self::Suspended => 'Suspendue',
        };
    }

    public function allowsAccess(): bool
    {
        return $this !== self::Suspended;
    }
}

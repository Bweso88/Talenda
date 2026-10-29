<?php

declare(strict_types=1);

namespace App\Modules\Finances\Enums;

use Filament\Support\Contracts\HasLabel;

enum FeeBilling: string implements HasLabel
{
    // Dû en une fois, à l'inscription (frais d'inscription, tenue…).
    case Upfront = 'upfront';
    // Réparti selon le plan de paiement de la famille (scolarité).
    case Spread = 'spread';

    public function getLabel(): string
    {
        return match ($this) {
            self::Upfront => 'À l\'inscription',
            self::Spread => 'Selon le plan de paiement',
        };
    }
}

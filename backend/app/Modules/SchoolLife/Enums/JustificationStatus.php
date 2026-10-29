<?php

declare(strict_types=1);

namespace App\Modules\SchoolLife\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Un justificatif saisi par l'école est accepté d'office ; celui qu'un
 * parent déposera depuis l'app mobile sera « à examiner ».
 */
enum JustificationStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'À examiner',
            self::Accepted => 'Accepté',
            self::Rejected => 'Refusé',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Accepted => 'success',
            self::Rejected => 'danger',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Communication\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SmsStatus: string implements HasColor, HasLabel
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Queued => 'En attente',
            self::Sent => 'Envoyé',
            self::Failed => 'Échec',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Queued => 'warning',
            self::Sent => 'success',
            self::Failed => 'danger',
        };
    }
}

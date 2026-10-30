<?php

declare(strict_types=1);

namespace App\Modules\Finances\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CashOperationStatus: string implements HasColor, HasLabel
{
    case PendingApproval = 'pending_approval';
    case Rejected = 'rejected';
    case Done = 'done';
    case Outstanding = 'outstanding';
    case Settled = 'settled';
    case InTransit = 'in_transit';
    case Received = 'received';
    case Cancelled = 'cancelled';
    case Reversed = 'reversed';

    public function getLabel(): string
    {
        return match ($this) {
            self::PendingApproval => 'À valider',
            self::Rejected => 'Refusée',
            self::Done => 'Payée',
            self::Outstanding => 'À justifier',
            self::Settled => 'Justifiée',
            self::InTransit => 'En route',
            self::Received => 'Reçu',
            self::Cancelled => 'Annulé',
            self::Reversed => 'Annulée',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PendingApproval, self::Outstanding, self::InTransit => 'warning',
            self::Rejected, self::Cancelled, self::Reversed => 'danger',
            self::Done, self::Settled, self::Received => 'success',
        };
    }
}

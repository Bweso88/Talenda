<?php

declare(strict_types=1);

namespace App\Modules\Finances\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Cash = 'cash';
    case MobileMoney = 'mobile_money';
    case BankTransfer = 'bank_transfer';
    case Cheque = 'cheque';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cash => 'Espèces',
            self::MobileMoney => 'Mobile money (MTN, Airtel)',
            self::BankTransfer => 'Virement bancaire',
            self::Cheque => 'Chèque',
        };
    }

    /**
     * Tout moyen autre que les espèces exige une référence vérifiable
     * (numéro de transaction, de virement, de chèque).
     */
    public function requiresReference(): bool
    {
        return $this !== self::Cash;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Finances\Enums;

use Filament\Support\Contracts\HasLabel;

enum CashRegisterKind: string implements HasLabel
{
    case Cash = 'cash';
    case MobileMoney = 'mobile_money';
    case Bank = 'bank';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cash => 'Caisse (espèces)',
            self::MobileMoney => 'Mobile money',
            self::Bank => 'Banque',
        };
    }

    /**
     * Compte SYSCOHADA proposé : 5711 caisse, 5521 monnaie électronique,
     * 5211 banque.
     */
    public function defaultAccount(): string
    {
        return (string) config("talenda.accounting.register_accounts.{$this->value}");
    }

    /**
     * Nature de caisse qui reçoit un paiement fait par ce moyen.
     */
    public static function receiving(PaymentMethod $method): self
    {
        return match ($method) {
            PaymentMethod::Cash => self::Cash,
            PaymentMethod::MobileMoney => self::MobileMoney,
            PaymentMethod::BankTransfer, PaymentMethod::Cheque => self::Bank,
        };
    }
}

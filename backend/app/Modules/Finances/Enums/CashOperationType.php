<?php

declare(strict_types=1);

namespace App\Modules\Finances\Enums;

use Filament\Support\Contracts\HasLabel;

enum CashOperationType: string implements HasLabel
{
    case Expense = 'expense';
    case Salary = 'salary';
    case Advance = 'advance';
    case Transfer = 'transfer';

    public function getLabel(): string
    {
        return match ($this) {
            self::Expense => 'Dépense',
            self::Salary => 'Salaire',
            self::Advance => 'Avance à justifier',
            self::Transfer => 'Virement entre caisses',
        };
    }

    /**
     * Sortie d'argent soumise au seuil de validation de la direction
     * (un virement reste dans l'école : il n'y est pas soumis).
     */
    public function needsApprovalAboveThreshold(): bool
    {
        return $this !== self::Transfer;
    }
}

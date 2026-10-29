<?php

declare(strict_types=1);

namespace App\Modules\Finances\Support;

use Illuminate\Support\Carbon;

/**
 * Une échéance de l'échéancier d'une facture, avec ce qui y a été affecté.
 */
final class StatementInstallment
{
    public function __construct(
        public readonly string $label,
        public readonly Carbon $dueOn,
        public readonly int $amount,
        public readonly int $paid,
        public readonly bool $isOverdue,
    ) {}

    public function remaining(): int
    {
        return $this->amount - $this->paid;
    }

    public function isSettled(): bool
    {
        return $this->remaining() <= 0;
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->isSettled() => 'Payé',
            $this->isOverdue => $this->paid > 0 ? 'En retard (partiel)' : 'En retard',
            $this->paid > 0 => 'Partiel',
            default => 'À venir',
        };
    }
}

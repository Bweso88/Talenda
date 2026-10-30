<?php

declare(strict_types=1);

namespace App\Modules\Finances\Policies;

use App\Models\User;
use App\Modules\Finances\Models\Payment;

final class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('finance.view');
    }

    public function view(User $user): bool
    {
        return $user->can('finance.view');
    }

    public function create(User $user): bool
    {
        return $user->can('payment.record');
    }

    public function update(): bool
    {
        return false;
    }

    public function delete(): bool
    {
        return false;
    }

    /**
     * Séparation des tâches (§12) : on n'annule pas son propre encaissement.
     */
    public function reverse(User $user, Payment $payment): bool
    {
        return $user->can('payment.reverse')
            && $payment->isValid()
            && $payment->recorded_by !== $user->id;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Finances\Policies;

use App\Models\User;

/**
 * Opérations de caisse (docs/TRESORERIE.md). Les règles fines (caisse
 * tenue, seuil, séparation des tâches) sont dans
 * App\Modules\Finances\Actions\CashOperations.
 */
final class CashOperationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('treasury.operate') || $user->can('treasury.approve');
    }

    public function view(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('treasury.operate');
    }

    public function update(): bool
    {
        return false;
    }

    public function delete(): bool
    {
        return false;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Finances\Policies;

use App\Models\User;
use App\Modules\Core\Contracts\TracksUsage;

/**
 * Paramètres financiers : types de frais, grille tarifaire, plans de
 * paiement, caisses.
 */
final class FinanceSettingsPolicy
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
        return $user->can('finance.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('finance.manage');
    }

    public function delete(User $user, TracksUsage $model): bool
    {
        return $user->can('finance.manage') && ! $model->isInUse();
    }
}

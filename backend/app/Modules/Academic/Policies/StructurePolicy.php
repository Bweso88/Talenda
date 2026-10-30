<?php

declare(strict_types=1);

namespace App\Modules\Academic\Policies;

use App\Models\User;
use App\Modules\Core\Contracts\TracksUsage;

/**
 * Éléments de structure de l'école : cycles, niveaux, séries, matières.
 * Les permissions sont évaluées dans l'école active (spatie, team = tenant).
 */
final class StructurePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('structure.view');
    }

    public function view(User $user): bool
    {
        return $user->can('structure.view');
    }

    public function create(User $user): bool
    {
        return $user->can('structure.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('structure.manage');
    }

    public function delete(User $user, TracksUsage $model): bool
    {
        return $user->can('structure.manage') && ! $model->isInUse();
    }
}

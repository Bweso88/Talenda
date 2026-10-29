<?php

declare(strict_types=1);

namespace App\Modules\SchoolLife\Policies;

use App\Models\User;

/**
 * Discipline : réservée à la vie scolaire et à la direction.
 */
final class DisciplinePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('discipline.manage');
    }

    public function view(User $user): bool
    {
        return $user->can('discipline.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('discipline.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('discipline.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('discipline.manage');
    }
}

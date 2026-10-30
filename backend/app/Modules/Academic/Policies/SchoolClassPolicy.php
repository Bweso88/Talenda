<?php

declare(strict_types=1);

namespace App\Modules\Academic\Policies;

use App\Models\User;
use App\Modules\Academic\Models\SchoolClass;

final class SchoolClassPolicy
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

    /**
     * Les classes d'une année clôturée sont figées (§9).
     */
    public function update(User $user, SchoolClass $schoolClass): bool
    {
        return $user->can('structure.manage') && ! $schoolClass->schoolYear->isClosed();
    }

    /**
     * Une classe qui a déjà eu des élèves reste dans l'historique.
     */
    public function delete(User $user, SchoolClass $schoolClass): bool
    {
        return $this->update($user, $schoolClass) && ! $schoolClass->enrollments()->exists();
    }
}

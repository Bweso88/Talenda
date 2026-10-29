<?php

declare(strict_types=1);

namespace App\Modules\Academic\Policies;

use App\Models\User;
use App\Modules\Academic\Enums\SchoolYearStatus;
use App\Modules\Academic\Models\SchoolYear;

final class SchoolYearPolicy
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
        return $user->can('school_year.manage');
    }

    /**
     * Une année clôturée ne se modifie plus (§9).
     */
    public function update(User $user, SchoolYear $schoolYear): bool
    {
        return $user->can('school_year.manage') && ! $schoolYear->isClosed();
    }

    /**
     * Seule une année encore en préparation et sans classe peut être
     * supprimée (erreur de saisie).
     */
    public function delete(User $user, SchoolYear $schoolYear): bool
    {
        return $user->can('school_year.manage')
            && $schoolYear->status === SchoolYearStatus::Draft
            && ! $schoolYear->schoolClasses()->exists();
    }
}

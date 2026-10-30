<?php

declare(strict_types=1);

namespace App\Modules\Academic\Policies;

use App\Models\User;
use App\Modules\Academic\Models\StaffMember;

final class StaffMemberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('staff.view');
    }

    public function view(User $user): bool
    {
        return $user->can('staff.view');
    }

    public function create(User $user): bool
    {
        return $user->can('staff.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('staff.manage');
    }

    /**
     * Un membre qui a un accès ou qui enseigne se désactive au lieu d'être
     * supprimé : son historique doit rester lisible.
     */
    public function delete(User $user, StaffMember $staffMember): bool
    {
        return $user->can('staff.manage')
            && ! $staffMember->hasAccess()
            && ! $staffMember->classSubjects()->exists();
    }

    public function grantAccess(User $user, StaffMember $staffMember): bool
    {
        return $user->can('staff.manage') && ! $staffMember->hasAccess();
    }
}

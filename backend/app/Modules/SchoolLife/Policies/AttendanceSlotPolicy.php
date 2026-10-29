<?php

declare(strict_types=1);

namespace App\Modules\SchoolLife\Policies;

use App\Models\User;
use App\Modules\SchoolLife\Models\AttendanceSlot;

/**
 * Créneaux d'appel : paramétrés par la vie scolaire.
 */
final class AttendanceSlotPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('attendance.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('attendance.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('attendance.manage');
    }

    public function delete(User $user, AttendanceSlot $slot): bool
    {
        return $user->can('attendance.manage') && ! $slot->isInUse();
    }
}
